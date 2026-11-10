<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Persistence\Migrations;

use App\Context\Identity\Infrastructure\Persistence\Migrations\Version20261110045220;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use App\Tests\Support\Context\Identity\Domain\Entity\StoredAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Library\Strings\Model\KeysBeforeComposition;
use App\Tests\Support\MigrationUp;
use Doctrine\DBAL\Exception\DriverException;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class Version20261110045220Test extends KernelTestCase
{
    public function testDecomposedRoleNamesAndEmailsAreComposed(): void
    {
        $role = Roles::named('Choir leader');
        $account = Accounts::approved('sara@example.org');
        $this->database()->persisted($role, $account);
        $this->storeRoleAsTypedBefore($role->id(), "Chorleiter Mu\u{0308}hle");
        $this->storeEmail($account->id(), "mu\u{0308}ller@example.org");

        $this->migrate();

        self::assertSame(
            ['name' => "Chorleiter M\u{00FC}hle", 'name_key' => "chorleiter_m\u{00FC}hle"],
            $this
                ->database()
                ->connection()
                ->fetchAssociative('SELECT name, name_key FROM identity.role WHERE id = ?', [$role->id()->toRfc4122()]),
        );
        $this->database()->entityManager()->clear();
        self::assertTrue(
            new StoredAccounts(self::getContainer())
                ->withEmail("Mu\u{0308}ller@example.org")
                ->id()
                ->equals($account->id()),
        );
    }

    public function testRoleNamesAndEmailsThatWouldBeTheSameStopTheMigrationAndAreListed(): void
    {
        $role = Roles::named("Chorleiter M\u{00FC}hle");
        $roleTwin = Roles::named('Choir leader');
        $account = Accounts::approved("m\u{00FC}ller@example.org");
        $accountTwin = Accounts::approved('sara@example.org');
        $this->database()->persisted($role, $roleTwin, $account, $accountTwin);
        $this->storeRoleAsTypedBefore($roleTwin->id(), "Chorleiter Mu\u{0308}hle");
        $this->storeEmail($accountTwin->id(), "mu\u{0308}ller@example.org");

        $message = $this->refusal();

        self::assertStringContainsString(sprintf("role %s 'Chorleiter M", $role->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf("role %s 'Chorleiter M", $roleTwin->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf('account %s', $account->id()->toRfc4122()), $message);
        self::assertStringContainsString(sprintf('account %s', $accountTwin->id()->toRfc4122()), $message);
        self::assertStringNotContainsString('@', $message);
    }

    private function storeRoleAsTypedBefore(Uuid $id, string $name): void
    {
        $this
            ->database()
            ->connection()
            ->executeStatement('UPDATE identity.role SET name = ?, name_key = ? WHERE id = ?', [
                $name,
                KeysBeforeComposition::of($name),
                $id->toRfc4122(),
            ]);
    }

    private function storeEmail(Uuid $id, string $email): void
    {
        $this
            ->database()
            ->connection()
            ->executeStatement('UPDATE identity.app_user SET email = ? WHERE id = ?', [$email, $id->toRfc4122()]);
    }

    private function refusal(): string
    {
        try {
            $this
                ->database()
                ->connection()
                ->transactional($this->migrate(...));
        } catch (DriverException $refusal) {
            self::assertSame('P0001', $refusal->getSQLState());

            return $refusal->getMessage();
        }

        self::fail('The migration composed role names or emails that are the same.');
    }

    private function migrate(): void
    {
        $connection = $this->database()->connection();

        new MigrationUp($connection)->run(new Version20261110045220($connection, new NullLogger()));
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
