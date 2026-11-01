<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;

final class SignOutListenerTest extends ApiTestCase
{
    public function testSigningOutEndsTheSession(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');
        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('POST', '/api/auth/sign-out');
        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testSigningOutWithoutASessionSucceeds(): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-out');

        self::assertResponseStatusCodeSame(204);
    }

    public function testSignOutOnlyAcceptsPostAndKeepsTheSessionOtherwise(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');
        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('GET', '/api/auth/sign-out');
        self::assertResponseStatusCodeSame(405);

        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }
}
