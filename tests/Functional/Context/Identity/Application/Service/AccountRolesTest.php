<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\AccountRoles;
use App\Library\Problem\Exception\RuleViolation;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Lock\OtherSession;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AccountRolesTest extends KernelTestCase
{
    private const string ADMINISTRATOR_ROLE = '019a3f00-0000-7000-8000-000000000001';

    private ?OtherSession $otherSession = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->otherSession?->close();
        parent::tearDown();
    }

    public function testTwoAdministratorsTakingEachOthersRoleLeaveOne(): void
    {
        $first = $this->administrator('mariam@example.org');
        $second = $this->administrator('daniel@example.org');

        $this->take($second, by: $first);
        $refusal = null;

        try {
            $this->take($first, by: $second);
        } catch (RuleViolation $violation) {
            $refusal = $violation->getMessage();
        }

        self::assertSame('roles.lastAdministrator', $refusal);

        self::assertSame(1, new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM identity.user_role WHERE role_id = ?', [self::ADMINISTRATOR_ROLE]));
    }

    public function testTheRemovalWaitsForTheAdministrationLock(): void
    {
        $first = $this->administrator('mariam@example.org');
        $second = $this->administrator('daniel@example.org');
        $this->otherSession = new OtherSession(self::getContainer());
        $this->otherSession->holdAdministrationLock();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("SET lock_timeout = '100ms'");

        $this->expectException(DriverException::class);

        $this->take($second, by: $first);
    }

    private function administrator(string $email): string
    {
        $id = new SignInAccounts(self::getContainer())->add($email);
        new RoleGrants(self::getContainer())->grant($id, 'administrator');

        return $id;
    }

    private function take(string $account, string $by): void
    {
        $roles = self::getContainer()->get(AccountRoles::class);
        self::assertInstanceOf(AccountRoles::class, $roles);

        $roles->take($account, self::ADMINISTRATOR_ROLE, $by);
    }
}
