<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;

final class AccountStatusListenerTest extends ApiTestCase
{
    private const string EMAIL = 'mariam.hanna@example.org';

    public function testAnAccountTheOfficeCreatedSignsInAtOnce(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL);

        $this->signIn();
        self::assertResponseStatusCodeSame(204);
        $this->issueToken();
        self::assertResponseStatusCodeSame(201);
    }

    public function testADisabledAccountIsRefusedWithItsReason(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL, ['disabled_at' => '2026-11-08 12:00:00+01']);

        $this->signIn();
        $this->assertProblem(403, 'auth.disabled');
        $this->issueToken();
        $this->assertProblem(403, 'auth.disabled');
    }

    private function signIn(): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => self::EMAIL, 'password' => SignInAccounts::TYPED]);
    }

    private function issueToken(): void
    {
        $this->jsonRequest('POST', '/api/auth/tokens', ['email' => self::EMAIL, 'password' => SignInAccounts::TYPED]);
    }
}
