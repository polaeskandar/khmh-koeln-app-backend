<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Interface\Api\Identity\EmailedLinks;
use App\Tests\Support\Interface\Api\Identity\Registrations;

final class AccountStatusListenerTest extends ApiTestCase
{
    private const string EMAIL = 'mariam.hanna@example.org';

    public function testARegistrationAsksForItsEmailAddressBeforeItWaitsForApproval(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());
        $token = new EmailedLinks(self::getContainer())->lastToken();

        $this->signIn();
        $this->assertProblem(403, 'auth.unconfirmed');
        $this->issueToken();
        $this->assertProblem(403, 'auth.unconfirmed');

        $this->jsonRequest('POST', '/api/auth/email-confirmations', ['token' => $token]);
        self::assertResponseStatusCodeSame(204);

        $this->signIn();
        $this->assertProblem(403, 'auth.pending');
        $this->issueToken();
        $this->assertProblem(403, 'auth.pending');
    }

    public function testAnApprovedAccountWithoutAConfirmedAddressSignsIn(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL, ['email_confirmed_at' => null]);

        $this->signIn();
        self::assertResponseStatusCodeSame(204);
        $this->issueToken();
        self::assertResponseStatusCodeSame(201);
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
