<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Privacy;

use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class OwnDataControllerErasureTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string REPLACEMENT = 'a brand new secret';

    public function testTheRightPasswordSchedulesTheErasureAndEndsTheSession(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->signInNamed('hoda@example.org', 'Hoda Farag', 'parent');

        $this->jsonRequest('GET', '/api/me/erasure-request');
        $this->assertJsonPath('erasureRequested', null);

        $this->requestErasure(['password' => SignInAccounts::TYPED, 'note' => 'Please delete everything.']);
        self::assertResponseStatusCodeSame(201);

        self::assertSame(
            ['receivedAt' => '2026-11-08T10:00:00+01:00', 'erasesOn' => '2026-11-22'],
            $this->responseJson(),
        );

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('admin@example.org', 'administrator');
        $this->jsonRequest('GET', '/api/data-requests');
        $this->assertJsonPath('open.0.channel', 'app');
        $this->assertJsonPath('open.0.kind', 'erasure');
        $this->assertJsonPath('open.0.requestedBy', 'Hoda Farag');
        $this->assertJsonPath('open.0.note', 'Please delete everything.');
        $this->assertJsonPath('open.0.dueOn', '2026-12-08');
        $this->assertJsonPath('open.0.erasesOn', '2026-11-22');
    }

    public function testAWrongPasswordChangesNothingAndIsThrottled(): void
    {
        $this->browser()->disableReboot();
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->requestErasure([]);
        $this->assertFieldError('password', 'accounts.currentPasswordWrong');

        foreach (range(start: 1, end: 5) as $attempt) {
            $this->requestErasure(['password' => SignInAccounts::MISTYPED . $attempt]);
            $this->assertFieldError('password', 'accounts.currentPasswordWrong');
        }

        $this->requestErasure(['password' => SignInAccounts::TYPED]);
        $this->assertProblem(429, 'auth.tooManyAttempts');

        $this->jsonRequest('GET', '/api/me/erasure-request');
        $this->assertJsonPath('erasureRequested', null);
        self::assertSame(0, $this->numberOf('SELECT count(*) FROM privacy.data_request'));
    }

    public function testAWrongPasswordIsTheOnlyFieldErrorAndCountsAgainstPasswordChanges(): void
    {
        $this->browser()->disableReboot();
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->requestErasure(['password' => SignInAccounts::MISTYPED, 'note' => str_repeat('a', times: 2001)]);
        $this->assertProblem(422, 'checkFields');

        $this->assertJsonPath('fieldErrors', [
            'note' => ['code' => 'validation.tooLong', 'params' => ['max' => 2000, 'limit' => 2000]],
        ]);

        foreach (range(start: 1, end: 5) as $attempt) {
            $this->requestErasure(['password' => SignInAccounts::MISTYPED . $attempt]);
            $this->assertProblem(422, 'checkFields');

            $this->assertJsonPath('fieldErrors', [
                'password' => ['code' => 'accounts.currentPasswordWrong', 'params' => []],
            ]);
        }

        $this->jsonRequest('POST', '/api/me/password', [
            'currentPassword' => SignInAccounts::TYPED,
            'newPassword' => self::REPLACEMENT,
        ]);

        $this->assertProblem(429, 'auth.tooManyAttempts');
    }

    public function testTheAccountsTokensEndAtOnce(): void
    {
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->jsonRequest('POST', '/api/auth/tokens', [
            'username' => 'hoda',
            'password' => SignInAccounts::TYPED,
        ]);

        $token = Type::of($this->responseJson()['token'] ?? null)->toString();

        $this->requestErasure(['password' => SignInAccounts::TYPED]);
        self::assertResponseStatusCodeSame(201);

        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest('GET', '/api/me', [], ['Authorization' => 'Bearer ' . $token]);
        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testTheLastAdministratorKeepsTheAccount(): void
    {
        $first = $this->signInWithRoles('admin@example.org', 'administrator');
        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('mariam@example.org', 'administrator');

        $this->requestErasure(['password' => SignInAccounts::TYPED]);
        self::assertResponseStatusCodeSame(201);

        $this->browser()->getCookieJar()->clear();
        $this->signInAs('admin@example.org', SignInAccounts::TYPED);
        $this->requestErasure(['password' => SignInAccounts::TYPED]);
        $this->assertProblem(409, 'roles.lastAdministrator');

        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM privacy.data_request'));

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.app_user WHERE id = ? AND erases_on IS NOT NULL', [
            $first,
        ]));
    }

    public function testAnOpenRequestFromTheAppIsNotMadeTwice(): void
    {
        $account = $this->signInWithRoles('hoda@example.org', 'parent');

        new Database(self::getContainer())
            ->connection()
            ->insert('privacy.data_request', [
                'id' => Uuid::v7()->toRfc4122(),
                'kind' => 'erasure',
                'app_user_id' => $account,
                'requested_by' => 'Hoda Farag',
                'channel' => 'app',
                'received_at' => '2026-11-02 12:00:00+01',
                'due_on' => '2026-12-02',
                'status' => 'open',
                'created_at' => '2026-11-02 12:00:00+01',
            ]);

        $this->requestErasure(['password' => SignInAccounts::TYPED]);
        $this->assertProblem(409, 'privacy.alreadyRequested');

        $this->jsonRequest('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testALongNoteOrAForeignOriginIsRefused(): void
    {
        $this->signInWithRoles('hoda@example.org', 'parent');

        $this->requestErasure(['password' => SignInAccounts::TYPED, 'note' => str_repeat('a', times: 2001)]);
        $this->assertFieldError('note', 'validation.tooLong', ['max' => 2000, 'limit' => 2000]);

        $this->jsonRequest(
            'POST',
            '/api/me/erasure-request',
            ['password' => SignInAccounts::TYPED],
            [
                'Origin' => 'https://evil.example',
            ],
        );

        $this->assertProblem(403, 'forbidden');

        $this->jsonRequest('GET', '/api/me/erasure-request');
        $this->assertJsonPath('erasureRequested', null);
    }

    /**
     * @param array<string, string> $body
     */
    private function requestErasure(array $body): void
    {
        $this->jsonRequest('POST', '/api/me/erasure-request', $body);
    }

    /**
     * @param list<string> $params
     */
    private function numberOf(string $sql, array $params = []): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql, $params),
        );
    }
}
