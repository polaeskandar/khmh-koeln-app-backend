<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use Override;
use Symfony\Component\HttpFoundation\Request;

final class SignInRateLimiterTest extends ApiTestCase
{
    private const string EMAIL = 'christine.adel@example.org';

    private const string PROXIES = 'TRUSTED_PROXIES';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        new SignInAccounts(self::getContainer())->add(self::EMAIL);
        $this->browser()->disableReboot();
    }

    #[Override]
    protected function tearDown(): void
    {
        $_ENV[self::PROXIES] = '';
        $_SERVER[self::PROXIES] = '';
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        parent::tearDown();
    }

    public function testTheSixthAttemptWithinTheWindowIsRefusedEvenWithTheRightPassword(): void
    {
        $this->failTimes(5, self::EMAIL);

        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED);

        $this->assertProblem(429, 'auth.tooManyAttempts');
        $retryAfter = (int) $this->browser()->getResponse()->headers->get('Retry-After');
        self::assertGreaterThan(0, $retryAfter);
        self::assertLessThanOrEqual(960, $retryAfter);
    }

    public function testSpacingAndCaseCountAsTheSameEmail(): void
    {
        $this->failTimes(5, '  Christine.ADEL@example.org ');

        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED);

        $this->assertProblem(429, 'auth.tooManyAttempts');
    }

    public function testASuccessResetsTheCount(): void
    {
        $this->failTimes(4, self::EMAIL);
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);

        $this->failTimes(4, self::EMAIL);
        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED);

        self::assertResponseStatusCodeSame(204);
    }

    public function testAForgedForwardedForIsIgnoredWithoutTrustedProxies(): void
    {
        foreach (range(start: 1, end: 5) as $client) {
            $this->tryToSignIn(self::EMAIL, 'wrong', ['X-Forwarded-For' => '203.0.113.' . $client]);
        }

        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED, ['X-Forwarded-For' => '203.0.113.99']);

        $this->assertProblem(429, 'auth.tooManyAttempts');
    }

    public function testBehindATrustedProxyEachForwardedClientHasItsOwnCount(): void
    {
        $_ENV[self::PROXIES] = '127.0.0.1';
        $_SERVER[self::PROXIES] = '127.0.0.1';
        $this->startClient([]);
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 5) as $attempt) {
            $this->tryToSignIn(self::EMAIL, 'wrong ' . $attempt, ['X-Forwarded-For' => '203.0.113.1']);
        }

        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED, ['X-Forwarded-For' => '203.0.113.2']);

        self::assertResponseStatusCodeSame(204);

        $this->tryToSignIn(self::EMAIL, SignInAccounts::TYPED, ['X-Forwarded-For' => '203.0.113.1']);

        $this->assertProblem(429, 'auth.tooManyAttempts');
    }

    private function failTimes(int $times, string $email): void
    {
        foreach (range(start: 1, end: $times) as $attempt) {
            $this->tryToSignIn($email, 'wrong ' . $attempt);

            $this->assertProblem(401, 'auth.invalidCredentials');
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function tryToSignIn(string $email, #[\SensitiveParameter] string $secret, array $headers = []): void
    {
        $this->jsonRequest(
            'POST',
            '/api/auth/sign-in',
            ['username' => Accounts::usernameOf($email), 'password' => $secret],
            $headers,
        );
    }
}
