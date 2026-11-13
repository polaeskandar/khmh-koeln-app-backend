<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Lock\OtherSession;
use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class UserRoleRemoveCommandTest extends KernelTestCase
{
    private ?OtherSession $otherSession = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->otherSession?->close();
        parent::tearDown();
    }

    public function testTheRoleIsTaken(): void
    {
        $this->account('daniel@example.org', 'servant');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['username' => 'daniel', 'role' => 'Servant']));

        self::assertSame(0, $this->roleCount());
        self::assertStringContainsString('Took the role Servant from daniel.', $tester->getDisplay());
    }

    #[DataProvider('refusals')]
    public function testARefusalExitsWithItsCodeAndChangesNothing(string $role, string $held, string $code): void
    {
        $this->account('daniel@example.org', $held);
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['username' => 'daniel', 'role' => $role]));

        self::assertStringContainsString($code, $tester->getDisplay());
        self::assertSame(1, $this->roleCount());
    }

    public function testTheRemovalWaitsForTheAdministrationLockBeforeReadingAnything(): void
    {
        $this->account('daniel@example.org', 'administrator');
        $tester = $this->tester();
        $this->otherSession = new OtherSession(self::getContainer());
        $this->otherSession->holdAdministrationLock();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("SET lock_timeout = '100ms'");

        $this->expectException(DriverException::class);

        $tester->execute(['username' => 'daniel', 'role' => 'Administrator']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'role not held' => ['Parent', 'servant', 'roles.notHeld'];
        yield 'last administrator' => ['Administrator', 'administrator', 'roles.lastAdministrator'];
    }

    private function account(string $email, string $role): void
    {
        $id = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'email' => $email,
            ]));

        new RoleGrants(self::getContainer())->grant($id, $role);
    }

    private function roleCount(): int
    {
        $count = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM identity.user_role');

        self::assertIsInt($count);

        return $count;
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:user:role:remove'));
    }
}
