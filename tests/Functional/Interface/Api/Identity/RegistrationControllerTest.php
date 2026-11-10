<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SpyPasswordHasherFactory;
use App\Tests\Support\Database;
use App\Tests\Support\Interface\Api\Identity\Registrations;
use App\Tests\Support\JsonBody;

final class RegistrationControllerTest extends ApiTestCase
{
    public function testAParentRegistrationWaitsForApproval(): void
    {
        $response = $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('{"status":"received"}', $response->getContent());

        $row = $this
            ->database()
            ->connection()
            ->fetchAssociative('SELECT * FROM identity.app_user WHERE email = ?', ['mariam.hanna@example.org']);

        self::assertIsArray($row);
        self::assertNull($row['approved_at'] ?? null);
        self::assertSame('Mariam Hanna', $row['display_name'] ?? null);
        self::assertSame('parent', $row['registered_as'] ?? null);
        self::assertSame('Youssef, 3rd Class', $row['registration_note'] ?? null);
        self::assertSame('ar', $row['language'] ?? null);
        self::assertSame('2026-11', $row['privacy_version'] ?? null);
        self::assertNotNull($row['privacy_accepted_at'] ?? null);
        self::assertSame(0, $this->rowsIn('identity.user_role'));

        $this->signInWith('mariam.hanna@example.org', SignInAccounts::TYPED);
        $this->assertProblem(403, 'auth.unconfirmed');
    }

    public function testEveryBrokenFieldIsReportedAtOnce(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
            'account' => ['email' => 'not-an-email', 'password' => substr(SignInAccounts::TYPED, offset: 0, length: 9)],
            'registration' => ['note' => '   '],
            'privacy' => ['accepted' => false],
        ]));

        $this->assertProblem(422, 'checkFields');
        $fieldErrors = JsonBody::asArray($this->responseJson()['fieldErrors'] ?? null, 'fieldErrors');

        self::assertSame(
            ['account.email', 'account.password', 'privacy.accepted', 'registration.note'],
            self::sortedKeys($fieldErrors),
        );

        $this->assertFieldError('account.email', 'accounts.emailInvalid');
        $this->assertFieldError('registration.note', 'accounts.childrenRequired');
        $this->assertFieldError('privacy.accepted', 'accounts.acceptPrivacy');
        self::assertSame(0, $this->rowsIn('identity.app_user'));
    }

    public function testUnknownChoicesAndOverlongValuesAreReportedTogether(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
            'account' => [
                'email' => str_repeat('a', times: 169) . '@example.org',
                'password' => str_repeat('a', times: 201),
                'language' => 'fr',
            ],
            'registration' => ['registeredAs' => 'guest'],
        ]));

        $this->assertProblem(422, 'checkFields');

        $this->assertJsonPath('fieldErrors', [
            'account.email' => ['code' => 'validation.tooLong', 'params' => ['max' => 180, 'limit' => 180]],
            'account.password' => [
                'code' => 'validation.tooLong',
                'params' => ['min' => 10, 'max' => 200, 'limit' => 200],
            ],
            'account.language' => ['code' => 'accounts.chooseLanguage', 'params' => ['choices' => '"en", "de", "ar"']],
            'registration.registeredAs' => [
                'code' => 'accounts.chooseRegisteredAs',
                'params' => ['choices' => '"parent", "servant", "member"'],
            ],
        ]);

        self::assertSame(0, $this->rowsIn('identity.app_user'));
    }

    public function testAnExistingEmailGetsTheSameAnswerAndChangesNothing(): void
    {
        new SignInAccounts(self::getContainer())->add('mariam.hanna@example.org');
        $this->browser()->disableReboot();
        $hashesBefore = $this->spyHasher()->hashCount();

        $response = $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
            'account' => ['email' => 'Mariam.Hanna@Example.org'],
        ]));

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('{"status":"received"}', $response->getContent());
        self::assertSame(1, $this->rowsIn('identity.app_user'));
        self::assertSame($hashesBefore + 1, $this->spyHasher()->hashCount());
    }

    public function testAStalePrivacyNoticeIsRefused(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent(['privacy' => [
            'version' => '2025-01',
        ]]));

        $this->assertProblem(409, 'accounts.privacyNoticeOutdated');
        self::assertSame(0, $this->rowsIn('identity.app_user'));
    }

    public function testTheTwentyFirstAttemptFromOneClientWithinAnHourIsRefused(): void
    {
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 20) as $attempt) {
            $this->jsonRequest('POST', '/api/auth/register', Registrations::parent([
                'account' => ['email' => 'parent' . $attempt . '@example.org'],
            ]));

            self::assertResponseStatusCodeSame(202);
        }

        $this->jsonRequest('POST', '/api/auth/register', Registrations::parent());

        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    private function signInWith(string $email, #[\SensitiveParameter] string $secret): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => $email, 'password' => $secret]);
    }

    private function spyHasher(): SpyPasswordHasherFactory
    {
        $spy = self::getContainer()->get(SpyPasswordHasherFactory::class);
        self::assertInstanceOf(SpyPasswordHasherFactory::class, $spy);

        return $spy;
    }

    private function rowsIn(string $table): int
    {
        $count = $this
            ->database()
            ->connection()
            ->fetchOne('SELECT count(*) FROM ' . $table);

        self::assertIsInt($count);

        return $count;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<array-key>
     */
    private static function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
