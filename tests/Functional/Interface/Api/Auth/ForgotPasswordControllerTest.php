<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Auth;

use App\Platform\Http\Contract\AfterResponse;
use App\Platform\Http\Service\TerminateQueue;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Workflow\Identity\RequestPasswordResetWorkflow;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

final class ForgotPasswordControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string EMAIL = 'daniel@example.org';

    private const string RECEIVED = '{"status":"received"}';

    public function testAnActiveAccountGetsOneEmailWithALinkThatWorksOnceForAnHour(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        new SignInAccounts(self::getContainer())->add(self::EMAIL, ['language' => 'en']);

        $token = $this->requestedToken(' Daniel@Example.org ');

        $this->jsonRequest('POST', '/api/auth/password-links', [
            'token' => $token,
            'password' => SignInAccounts::MISTYPED,
        ]);

        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('POST', '/api/auth/password-links', [
            'token' => $token,
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(422, 'auth.resetLinkInvalid');

        $late = $this->requestedToken(self::EMAIL);
        self::mockTime('2026-11-08T11:00:01+01:00');
        $this->jsonRequest('POST', '/api/auth/password-links', ['token' => $late, 'password' => SignInAccounts::TYPED]);
        $this->assertProblem(422, 'auth.resetLinkInvalid');
    }

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('silentAddresses')]
    public function testOtherAddressesGetNoEmailAndTheSameAnswer(array $columns): void
    {
        if ($columns !== []) {
            new SignInAccounts(self::getContainer())->add(self::EMAIL, $columns);
        }

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => self::EMAIL]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(self::RECEIVED, $this->browser()->getResponse()->getContent());
        self::assertEmailCount(0);
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function silentAddresses(): iterable
    {
        yield 'unknown' => [[]];
        yield 'pending' => [['approved_at' => null]];
        yield 'disabled' => [['disabled_at' => '2026-11-08 09:30:00+01']];
    }

    public function testTheAddressMustBeAnAddress(): void
    {
        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => ' ']);
        $this->assertFieldError('email', 'accounts.emailRequired');

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'daniel']);
        $this->assertFieldError('email', 'accounts.emailInvalid');
    }

    public function testTheLinkIsPreparedOnlyAfterTheResponse(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        new SignInAccounts(self::getContainer())->add(self::EMAIL, ['language' => 'en']);
        $requestPasswordReset = self::getContainer()->get(RequestPasswordResetWorkflow::class);
        $afterResponse = self::getContainer()->get(AfterResponse::class);
        self::assertInstanceOf(RequestPasswordResetWorkflow::class, $requestPasswordReset);
        self::assertInstanceOf(TerminateQueue::class, $afterResponse);

        $requestPasswordReset(self::EMAIL);
        self::assertSame(0, $this->tokens());

        $afterResponse->runAll();
        self::assertSame(1, $this->tokens());
    }

    public function testAnAddressAsLongAsAccountsMayHaveIsAccepted(): void
    {
        $email =
            str_repeat('a', times: 60)
            . '@'
            . implode('.', [
                str_repeat('b', times: 60),
                str_repeat('c', times: 60),
                str_repeat('d', times: 60),
                'org',
            ]);

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => $email]);

        self::assertResponseStatusCodeSame(202);
    }

    public function testTheFourthRequestForOneAddressIsRefused(): void
    {
        $this->browser()->disableReboot();

        foreach (['daniel@example.org', 'DANIEL@example.org', 'daniel@example.org'] as $email) {
            $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => $email]);
            self::assertResponseStatusCodeSame(202);
        }

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => self::EMAIL]);
        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    public function testTheEleventhRequestFromOneClientIsRefused(): void
    {
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 10) as $attempt) {
            $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'person' . $attempt . '@example.org']);
            self::assertResponseStatusCodeSame(202, 'attempt ' . $attempt);
        }

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'person11@example.org']);
        $this->assertProblem(429, 'auth.tooManyAttempts');
    }

    private function tokens(): int
    {
        $count = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT count(*) FROM identity.password_token');

        self::assertIsInt($count);

        return $count;
    }

    private function requestedToken(string $email): string
    {
        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => $email]);

        self::assertResponseStatusCodeSame(202);
        self::assertSame(self::RECEIVED, $this->browser()->getResponse()->getContent());
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('Set your KHMK Köln password', $message->getSubject());
        self::assertSame(self::EMAIL, ($message->getTo()[0] ?? null)?->getAddress());
        $matches = [];
        self::assertSame(1, preg_match('/#token=([A-Za-z0-9_-]{43})/', (string) $message->getTextBody(), $matches));

        return $matches[1] ?? '';
    }
}
