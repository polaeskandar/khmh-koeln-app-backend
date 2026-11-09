<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use SensitiveParameter;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ErasureCancellationControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string EMAIL = 'hoda@example.org';

    public function testSigningInAnswersTheErasureDateUntilItIsCancelled(): void
    {
        $this->scheduled();

        $this->signIn(SignInAccounts::TYPED);
        $this->assertProblem(403, 'auth.erasureScheduled', ['erasesOn' => '2026-11-22']);
        $this->jsonRequest('POST', '/api/auth/tokens', ['email' => self::EMAIL, 'password' => SignInAccounts::TYPED]);
        $this->assertProblem(403, 'auth.erasureScheduled', ['erasesOn' => '2026-11-22']);
        $this->signIn(SignInAccounts::MISTYPED);
        $this->assertProblem(401, 'auth.invalidCredentials');

        $this->cancel(' Hoda@Example.org ', SignInAccounts::TYPED);
        self::assertResponseStatusCodeSame(204);

        $this->signIn(SignInAccounts::TYPED);
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();

        self::assertSame(
            1,
            $this->numberOf(
                "SELECT count(*) FROM privacy.data_request WHERE status = 'withdrawn' AND closed_at IS NOT NULL AND closed_by IS NULL",
            ),
        );
    }

    public function testWrongCredentialsChangeNothing(): void
    {
        $this->scheduled();

        $this->cancel(self::EMAIL, SignInAccounts::MISTYPED);
        $this->assertProblem(401, 'auth.invalidCredentials');
        $this->cancel('nobody@example.org', SignInAccounts::TYPED);
        $this->assertProblem(401, 'auth.invalidCredentials');
        $this->jsonRequest('POST', '/api/auth/erasure-cancellation', []);
        $this->assertProblem(401, 'auth.invalidCredentials');

        $this->signIn(SignInAccounts::TYPED);
        $this->assertProblem(403, 'auth.erasureScheduled', ['erasesOn' => '2026-11-22']);
        self::assertSame(1, $this->numberOf("SELECT count(*) FROM privacy.data_request WHERE status = 'open'"));
    }

    public function testNothingScheduledIsAConflict(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL);

        $this->cancel(self::EMAIL, SignInAccounts::TYPED);

        $this->assertProblem(409, 'privacy.nothingScheduled');
    }

    public function testADisabledAccountStaysDisabled(): void
    {
        $this->scheduled();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement("UPDATE identity.app_user SET disabled_at = '2026-11-09 12:00:00+01'");

        $this->signIn(SignInAccounts::TYPED);
        $this->assertProblem(403, 'auth.disabled');

        $this->cancel(self::EMAIL, SignInAccounts::TYPED);
        self::assertResponseStatusCodeSame(204);
        $this->signIn(SignInAccounts::TYPED);
        $this->assertProblem(403, 'auth.disabled');
    }

    public function testAttemptsAreThrottledLikeSignIn(): void
    {
        $this->browser()->disableReboot();
        $this->scheduled();

        foreach (range(start: 1, end: 4) as $attempt) {
            $this->cancel(self::EMAIL, SignInAccounts::MISTYPED . $attempt);
            $this->assertProblem(401, 'auth.invalidCredentials');
        }

        $this->cancel(self::EMAIL, SignInAccounts::TYPED);
        self::assertResponseStatusCodeSame(204);
        $this->cancel(self::EMAIL, SignInAccounts::TYPED);
        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    private function scheduled(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInWithRoles(self::EMAIL, 'parent');
        $this->jsonRequest('POST', '/api/me/erasure-request', ['password' => SignInAccounts::TYPED]);
        self::assertResponseStatusCodeSame(201);
        $this->browser()->getCookieJar()->clear();
    }

    private function signIn(#[SensitiveParameter] string $password): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => self::EMAIL, 'password' => $password]);
    }

    private function cancel(string $email, #[SensitiveParameter] string $password): void
    {
        $this->jsonRequest('POST', '/api/auth/erasure-cancellation', ['email' => $email, 'password' => $password]);
    }

    private function numberOf(string $sql): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql),
        );
    }
}
