<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use SensitiveParameter;

final class TokenControllerSchemeTest extends ApiTestCase
{
    private const string EMAIL = 'mina@example.org';

    public function testTheSchemeIsReadWhateverItsCase(): void
    {
        $token = $this->issued();

        foreach (['bearer', 'BEARER', 'Bearer'] as $scheme) {
            $this->withToken($scheme . ' ' . $token, 'GET', '/api/me');
            self::assertResponseIsSuccessful();
            $this->assertJsonPath('account.username', Accounts::usernameOf(self::EMAIL));
        }
    }

    public function testAnAppChangesItsPasswordWithoutASession(): void
    {
        $token = $this->issued();

        $this->withToken('Bearer ' . $token, 'POST', '/api/me/password', [
            'currentPassword' => SignInAccounts::TYPED,
            'newPassword' => SignInAccounts::MISTYPED,
        ]);

        self::assertResponseStatusCodeSame(204);
        self::assertFalse($this->browser()->getResponse()->headers->has('Set-Cookie'));

        $this->withToken('Bearer ' . $token, 'GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function issued(): string
    {
        $id = new SignInAccounts(self::getContainer())->add(self::EMAIL);
        new RoleGrants(self::getContainer())->grant($id, 'servant');

        $this->jsonRequest('POST', '/api/auth/tokens', [
            'username' => Accounts::usernameOf(self::EMAIL),
            'password' => SignInAccounts::TYPED,
        ]);

        self::assertResponseStatusCodeSame(201);

        return Type::of($this->responseJson()['token'] ?? null)->toString();
    }

    /**
     * @param array<string, string> $body
     */
    private function withToken(
        #[SensitiveParameter]
        string $authorization,
        string $method,
        string $path,
        array $body = [],
    ): void {
        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest($method, $path, $body, ['Authorization' => $authorization]);
    }
}
