<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use Symfony\Component\HttpFoundation\Cookie;

final class SessionControllerTest extends ApiTestCase
{
    public function testTheRightPasswordOpensASessionInAProtectedCookie(): void
    {
        $id = new SignInAccounts(self::getContainer())->add('christine.adel@example.org');

        $response = $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => '  Christine.Adel ',
            'password' => SignInAccounts::TYPED,
        ]);

        self::assertSame(204, $response->getStatusCode());
        $cookie = $this->sessionCookie();
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());

        $this->jsonRequest('GET', '/api/testing/session');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('id', $id);

        self::assertStringContainsString(
            'no-store',
            (string) $this->browser()->getResponse()->headers->get('Cache-Control'),
        );
    }

    public function testSigningInAgainRotatesTheSession(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');
        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);
        $first = $this->sessionCookie()->getValue();

        $this->signInAs('christine.adel@example.org', SignInAccounts::TYPED);

        self::assertNotSame($first, $this->sessionCookie()->getValue());
    }

    public function testWithoutASessionAProtectedRouteIsUnauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/testing/session');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testABodyWithoutStringCredentialsIsAnInvalidRequest(): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => 'christine.adel',
            'password' => [SignInAccounts::TYPED],
        ]);

        $this->assertProblem(400, 'invalidRequest');

        $this->browser()->request(
            'POST',
            '/api/auth/sign-in',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"email":',
        );

        $this->assertProblem(400, 'invalidRequest');
    }

    public function testSignInOnlyAcceptsPost(): void
    {
        $this->jsonRequest('GET', '/api/auth/sign-in');

        self::assertResponseStatusCodeSame(405);
    }

    private function sessionCookie(): Cookie
    {
        $cookies = $this->browser()->getResponse()->headers->getCookies();
        self::assertCount(1, $cookies);
        $cookie = $cookies[0] ?? null;
        self::assertInstanceOf(Cookie::class, $cookie);

        return $cookie;
    }
}
