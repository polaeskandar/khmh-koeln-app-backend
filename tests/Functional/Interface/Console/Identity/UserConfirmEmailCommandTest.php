<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Console\Identity;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\EmailedLinks;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use App\Tests\Support\JsonBody;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserConfirmEmailCommandTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string EMAIL = 'mariam.hanna@example.org';

    public function testAConfirmedRegistrationReachesTheOfficeAndItsLinkIsVoid(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $token = $this->registered();
        $this->signInWithRoles('admin@example.org', 'administrator');
        self::assertSame(0, $this->requestCount());

        self::mockTime('2026-11-09T11:00:00+01:00');
        $tester = $this->console(self::EMAIL);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Confirmed mariam.hanna@example.org.', $tester->getDisplay());
        self::assertEmailCount(0);
        self::assertSame('2026-11-09 11:00:00+01', $this->confirmedAt());
        self::assertSame(1, $this->requestCount());

        $this->jsonRequest('POST', '/api/auth/email-confirmations', ['token' => $token]);
        $this->assertProblem(422, 'auth.confirmationLinkInvalid');

        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => self::EMAIL, 'password' => SignInAccounts::TYPED]);
        $this->assertProblem(403, 'auth.pending');
    }

    public function testConfirmingAgainChangesNothing(): void
    {
        self::mockTime('2026-11-09T10:00:00+01:00');
        $this->registered();
        $this->console(self::EMAIL);

        self::mockTime('2026-11-09T12:00:00+01:00');
        $tester = $this->console('Mariam.Hanna@Example.org');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Mariam.Hanna@Example.org is already confirmed.', $tester->getDisplay());
        self::assertSame('2026-11-09 10:00:00+01', $this->confirmedAt());
    }

    public function testAnUnknownAddressFails(): void
    {
        $tester = $this->console(self::EMAIL);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('notFound.account: no account has the email address', $tester->getDisplay());
    }

    private function registered(): string
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        self::assertResponseStatusCodeSame(202);

        return new EmailedLinks(self::getContainer())->lastToken();
    }

    private function console(string $email): CommandTester
    {
        $tester = new CommandTester(new Application($this->browser()->getKernel())->find('app:user:confirm-email'));
        $tester->execute(['email' => $email]);

        return $tester;
    }

    private function requestCount(): int
    {
        $this->jsonRequest('GET', '/api/account-requests');

        return count(JsonBody::asArray($this->responseJson()['items'] ?? null, 'items'));
    }

    private function confirmedAt(): ?string
    {
        return Type::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne('SELECT email_confirmed_at FROM identity.app_user WHERE email = ?', [self::EMAIL]),
        )->toStringOrNull();
    }
}
