<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;

final class MeControllerLanguageTest extends ApiTestCase
{
    public function testEveryAccountSavesItsLanguage(): void
    {
        foreach ([['administrator'], ['servant'], ['parent'], []] as $index => $roles) {
            $email = 'account' . $index . '@example.org';
            $this->signInWithRoles($email, ...$roles);
            $this->jsonRequest('GET', '/api/me');
            $before = $this->responseJson();

            $this->jsonRequest('PATCH', '/api/me', ['language' => 'ar']);

            self::assertResponseIsSuccessful();

            self::assertSame(
                array_replace_recursive($before, ['account' => ['language' => 'ar']]),
                $this->responseJson(),
            );

            self::assertSame('ar', $this->languageOf($email));
            $this->browser()->getCookieJar()->clear();
        }
    }

    public function testAnotherSessionStaysSignedInAndReadsTheNewLanguage(): void
    {
        $this->signInWithRoles('christine@example.org', 'servant');
        $other = $this->browser()->getCookieJar()->all();
        $this->browser()->getCookieJar()->clear();
        $this->signInAs('christine@example.org', SignInAccounts::TYPED);

        $this->jsonRequest('PATCH', '/api/me', ['language' => 'ar']);

        $this->browser()->getCookieJar()->clear();

        foreach ($other as $cookie) {
            $this->browser()->getCookieJar()->set($cookie);
        }

        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('account.language', 'ar');
    }

    /**
     * @param array<string, string|null> $body
     */
    #[DataProvider('invalidBodies')]
    public function testOnlyAKnownLanguageIsAccepted(array $body, string $field, string $code): void
    {
        $this->signInWithRoles('christine@example.org', 'servant');

        $this->jsonRequest('PATCH', '/api/me', $body);

        $this->assertFieldError($field, $code);
        self::assertSame('de', $this->languageOf('christine@example.org'));
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'unknown' => [['language' => 'fr'], 'language', 'validation.invalidType'];
        yield 'null' => [['language' => null], 'language', 'validation.invalidType'];
        yield 'missing' => [[], 'language', 'validation.invalidType'];
        yield 'another field' => [
            ['language' => 'ar', 'displayName' => 'Christine'],
            'displayName',
            'validation.unexpectedField',
        ];
    }

    public function testASessionIsNeeded(): void
    {
        $this->jsonRequest('PATCH', '/api/me', ['language' => 'ar']);

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    private function languageOf(string $email): string
    {
        $language = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT language FROM identity.app_user WHERE email = ?', [$email]);

        self::assertIsString($language);

        return $language;
    }
}
