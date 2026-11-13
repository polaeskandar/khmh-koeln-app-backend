<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Lock\OtherSession;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use Doctrine\DBAL\Exception\DriverException;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserDisableCommandTest extends ApiTestCase
{
    private const string EMAIL = 'daniel@example.org';

    private ?OtherSession $otherSession = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->otherSession?->close();
        parent::tearDown();
    }

    public function testDisablingWaitsForTheAdministrationLockBeforeReadingAnything(): void
    {
        $this->servant();
        $this->otherSession = new OtherSession(self::getContainer());
        $this->otherSession->holdAdministrationLock();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("SET lock_timeout = '100ms'");

        $this->expectException(DriverException::class);

        $this->console('app:user:disable');
    }

    public function testADisabledAccountCanNotSignInAndItsSessionEnds(): void
    {
        $this->servant();
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);

        self::assertSame(Command::SUCCESS, $this->console('app:user:disable'));

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => Accounts::usernameOf(self::EMAIL),
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(401, 'auth.invalidCredentials');
    }

    public function testEnablingRestoresSignInButNotTheOldSession(): void
    {
        $this->servant();
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);

        $this->console('app:user:disable');
        $this->console('app:user:enable');

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);
        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testRepeatingAChangeChangesNothing(): void
    {
        $this->servant();

        $this->console('app:user:disable');
        $this->console('app:user:disable');
        self::assertSame(1, $this->authVersion());

        $this->console('app:user:enable');
        $this->console('app:user:enable');
        self::assertSame(1, $this->authVersion());
    }

    public function testTheOnlyActiveAdministratorStaysEnabled(): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::EMAIL);
        new RoleGrants(self::getContainer())->grant($id, 'administrator');

        self::assertSame(Command::FAILURE, $this->console('app:user:disable'));
        self::assertSame(0, $this->authVersion());
    }

    public function testAnUnknownUsernameFails(): void
    {
        self::assertSame(Command::FAILURE, $this->console('app:user:disable'));
        self::assertSame(Command::FAILURE, $this->console('app:user:enable'));
    }

    private function servant(): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::EMAIL);
        new RoleGrants(self::getContainer())->grant($id, 'servant');
    }

    private function console(string $command): int
    {
        return new CommandTester(new Application($this->browser()->getKernel())->find($command))->execute([
            'username' => Accounts::usernameOf(self::EMAIL),
        ]);
    }

    private function authVersion(): int
    {
        $version = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT auth_version FROM identity.app_user WHERE username = ?', [Accounts::usernameOf(
                self::EMAIL,
            )]);

        self::assertIsInt($version);

        return $version;
    }
}
