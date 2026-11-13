<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Access\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use SensitiveParameter;

final class TokenControllerRefusalTest extends ApiTestCase
{
    private const string EMAIL = 'mina@example.org';

    /**
     * @param array<string, string|null> $columns
     */
    #[DataProvider('refusals')]
    public function testRefusalsAnswerExactlyLikeCookieSignIn(
        array $columns,
        #[SensitiveParameter]
        string $password,
    ): void {
        if ($columns !== ['unknown' => null]) {
            $this->account($columns);
        }

        $body = ['username' => Accounts::usernameOf(self::EMAIL), 'password' => $password];
        $this->jsonRequest('POST', '/api/auth/sign-in', $body);
        $cookie = [$this->browser()->getResponse()->getStatusCode(), $this->browser()->getResponse()->getContent()];
        $this->jsonRequest('POST', '/api/auth/tokens', [...$body, 'deviceName' => 'iPad']);

        self::assertSame($cookie, [
            $this->browser()->getResponse()->getStatusCode(),
            $this->browser()->getResponse()->getContent(),
        ]);

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.api_token'));
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown email' => [['unknown' => null], SignInAccounts::TYPED];
        yield 'wrong password' => [[], SignInAccounts::MISTYPED];
        yield 'disabled' => [['disabled_at' => '2026-11-01 10:00:00+01'], SignInAccounts::TYPED];
    }

    public function testTokenAndCookieSignInsShareTheThrottle(): void
    {
        $this->browser()->disableReboot();
        $this->account();

        foreach (range(start: 1, end: 5) as $attempt) {
            $path = ($attempt % 2) === 0 ? '/api/auth/sign-in' : '/api/auth/tokens';

            $this->jsonRequest('POST', $path, [
                'username' => Accounts::usernameOf(self::EMAIL),
                'password' => SignInAccounts::MISTYPED,
            ]);

            $this->assertProblem(401, 'auth.invalidCredentials');
        }

        $this->jsonRequest('POST', '/api/auth/tokens', [
            'username' => Accounts::usernameOf(self::EMAIL),
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(array $columns = []): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::EMAIL, $columns);
        new RoleGrants(self::getContainer())->grant($id, 'servant');
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
