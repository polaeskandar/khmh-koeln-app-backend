<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use Symfony\Component\BrowserKit\Cookie;

final class PasswordControllerTest extends ApiTestCase
{
    private const string NEW = 'a brand new secret';

    public function testTheNewPasswordSignsInAndTheOldOneNoLonger(): void
    {
        foreach (['servant', 'parent'] as $role) {
            $email = $role . '@example.org';
            $this->signInWithRoles($email, $role);

            $this->change(SignInAccounts::TYPED, self::NEW);
            self::assertResponseStatusCodeSame(204);
            $this->jsonRequest('GET', '/api/me');
            self::assertResponseIsSuccessful();

            $this->browser()->getCookieJar()->clear();
            $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => $email, 'password' => SignInAccounts::TYPED]);
            $this->assertProblem(401, 'auth.invalidCredentials');
            $this->signInAs($email, self::NEW);
            $this->browser()->getCookieJar()->clear();
        }
    }

    public function testOtherSessionsEndAndThisOneMovesToANewId(): void
    {
        $this->signInWithRoles('christine@example.org', 'servant');
        $other = $this->cookies();
        $this->browser()->getCookieJar()->clear();
        $this->signInAs('christine@example.org', SignInAccounts::TYPED);
        $before = $this->cookies();

        $this->change(SignInAccounts::TYPED, self::NEW);
        self::assertResponseStatusCodeSame(204);
        $after = $this->cookies();
        self::assertNotEquals($before, $after);

        foreach ([[$after, 200], [$before, 401], [$other, 401]] as [$cookies, $status]) {
            $this->useCookies($cookies);
            $this->jsonRequest('GET', '/api/me');
            self::assertResponseStatusCodeSame($status);
        }
    }

    public function testWrongCurrentPasswordsAreRefusedThenThrottled(): void
    {
        $this->browser()->disableReboot();
        $account = $this->signInWithRoles('christine@example.org', 'servant');
        $hash = $this->hashOf($account);

        foreach (range(start: 1, end: 5) as $attempt) {
            $this->change('wrong password ' . $attempt, self::NEW);
            $this->assertFieldError('currentPassword', 'accounts.currentPasswordWrong');
        }

        $this->change(SignInAccounts::TYPED, self::NEW);
        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
        self::assertSame($hash, $this->hashOf($account));
    }

    public function testTheNewPasswordNeedsTenToTwoHundredCharacters(): void
    {
        $this->signInWithRoles('christine@example.org', 'servant');

        $this->change(SignInAccounts::TYPED, str_repeat('a', times: 9));
        $this->assertFieldError('newPassword', 'accounts.passwordTooShort', ['min' => 10]);

        $this->change(SignInAccounts::TYPED, str_repeat('a', times: 201));
        $this->assertFieldError('newPassword', 'validation.tooLong', ['max' => 200]);
    }

    public function testASessionIsNeeded(): void
    {
        $this->change(SignInAccounts::TYPED, self::NEW);

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function change(string $current, string $new): void
    {
        $this->jsonRequest('POST', '/api/me/password', ['currentPassword' => $current, 'newPassword' => $new]);
    }

    /**
     * @return array<array-key, Cookie>
     */
    private function cookies(): array
    {
        return $this->browser()->getCookieJar()->all();
    }

    /**
     * @param array<array-key, Cookie> $cookies
     */
    private function useCookies(array $cookies): void
    {
        $jar = $this->browser()->getCookieJar();
        $jar->clear();

        foreach ($cookies as $cookie) {
            $jar->set($cookie);
        }
    }

    private function hashOf(string $account): string
    {
        $hash = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT password_hash FROM identity.app_user WHERE id = ?', [$account]);

        self::assertIsString($hash);

        return $hash;
    }
}
