<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Domain\Entity;

use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineUserRepository;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends KernelTestCase
{
    public function testAnEmailBelongsToOneAccount(): void
    {
        $this->database()->connection()->insert('identity.app_user', Accounts::row());

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, static fn (Connection $connection): int|string => $connection->insert(
            'identity.app_user',
            Accounts::row(),
        ));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('violations')]
    public function testTheDatabaseRefusesAnInvalidAccount(array $columns): void
    {
        $this->database()->assertRefused(Database::CHECK_VIOLATION, static fn (Connection $connection): int|string => $connection->insert(
            'identity.app_user',
            Accounts::row($columns),
        ));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function violations(): iterable
    {
        yield 'email in mixed case' => [['email' => 'Sara@example.org']];
        yield 'email with spaces' => [['email' => ' sara@example.org']];
        yield 'blank display name' => [['display_name' => '  ']];
        yield 'unknown language' => [['language' => 'fr']];
        yield 'unknown registration' => [['approved_at' => null, 'registered_as' => 'admin']];
        yield 'registration kept after approval' => [['registered_as' => 'servant']];
        yield 'note kept after approval' => [['registration_note' => 'Sunday School']];
        yield 'street only' => [['street' => 'Domstraße 1']];
        yield 'no city' => [['street' => 'Domstraße 1', 'postal_code' => '50667']];
    }

    public function testAPendingServantWithANoteAndAFullAddressIsAccepted(): void
    {
        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'approved_at' => null,
                'registered_as' => 'servant',
                'registration_note' => 'I teach the 3rd class.',
                'street' => 'Domstraße 1',
                'postal_code' => '50667',
                'city' => 'Köln',
            ]));

        self::assertSame(1, $this->database()->connection()->fetchOne('SELECT count(*) FROM identity.app_user'));
    }

    public function testDeletingTheApproverKeepsTheAccount(): void
    {
        $connection = $this->database()->connection();
        $approverId = Uuid::v7()->toRfc4122();
        $connection->insert('identity.app_user', Accounts::row(['id' => $approverId, 'email' => 'admin@example.org']));
        $connection->insert('identity.app_user', Accounts::row(['approved_by' => $approverId]));

        $connection->delete('identity.app_user', ['id' => $approverId]);

        self::assertNull($connection->fetchOne('SELECT approved_by FROM identity.app_user'));
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('inactiveAccounts')]
    public function testOnlyAnApprovedAccountThatIsNotDisabledIsActive(array $columns, bool $active): void
    {
        $this->database()->connection()->insert('identity.app_user', Accounts::row($columns));

        $user = new DoctrineUserRepository($this->database()->entityManager())->findByEmail('sara@example.org');

        self::assertSame($active, $user?->status()->isActive());
    }

    /**
     * @return iterable<string, array{array<string, string|null>, bool}>
     */
    public static function inactiveAccounts(): iterable
    {
        yield 'approved' => [[], true];
        yield 'pending' => [['approved_at' => null], false];
        yield 'disabled' => [['disabled_at' => '2026-11-09 08:00:00+01'], false];
        yield 'disabled while pending' => [['approved_at' => null, 'disabled_at' => '2026-11-09 08:00:00+01'], false];
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
