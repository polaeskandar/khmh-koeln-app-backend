<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Checksum\Model\Checksum;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use SensitiveParameter;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\ApplicationTester;

final class TokenControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string EMAIL = 'mina@example.org';

    public function testATokenSignsInWithoutASessionOrCookie(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $this->account();

        $sessions = $this->numberOf('SELECT count(*) FROM public.sessions');
        $token = $this->issued();

        $this->assertJsonPath('expiresAt', '2027-02-06T12:00:00+01:00');
        self::assertFalse($this->browser()->getResponse()->headers->has('Set-Cookie'));

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM identity.api_token WHERE token_hash = ?', [Checksum::sha256(
            $token,
        )]));

        self::assertSame(0, $this->numberOf('SELECT count(*) FROM identity.api_token WHERE token_hash = ?', [$token]));

        self::assertSame(
            1,
            $this->numberOf("SELECT count(*) FROM identity.api_token WHERE device_name = 'Mina''s iPhone'"),
        );

        $this->withToken($token, 'GET', '/api/me');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('account.email', self::EMAIL);
        self::assertFalse($this->browser()->getResponse()->headers->has('Set-Cookie'));
        self::assertSame($sessions, $this->numberOf('SELECT count(*) FROM public.sessions'));
    }

    public function testRevokingTheCurrentTokenLeavesTheOthers(): void
    {
        $this->account();
        $phone = $this->issued();
        $tablet = $this->issued();

        $this->withToken($phone, 'DELETE', '/api/auth/tokens/current');
        self::assertResponseStatusCodeSame(204);

        $this->withToken($phone, 'GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');
        $this->withToken($tablet, 'GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testExpiredTokensAndTokensOfADisabledAccountAreRefused(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $this->account();
        $token = $this->issued();

        self::mockTime('2027-02-06T12:00:01+01:00');
        $this->withToken($token, 'GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $fresh = $this->issued();
        $this->console(['command' => 'app:user:disable', 'email' => self::EMAIL]);
        $this->withToken($fresh, 'GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        self::assertSame(
            2,
            $this->numberOf(
                'SELECT count(*) FROM identity.api_token WHERE revoked_at IS NOT NULL OR expires_at < now()',
            ),
        );
    }

    public function testRevokingNeedsABearerTokenNotACookie(): void
    {
        $this->account();
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);

        $this->jsonRequest('DELETE', '/api/auth/tokens/current');

        $this->assertProblem(401, 'auth.unauthenticated');
    }

    public function testAGarbageTokenBeatsAValidCookieFromAForeignOrigin(): void
    {
        $this->account();
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);

        $this->jsonRequest(
            'PUT',
            '/api/me/language',
            ['language' => 'ar'],
            [
                'Authorization' => 'Bearer garbage',
                'Origin' => 'https://evil.example',
            ],
        );

        $this->assertProblem(401, 'auth.unauthenticated');
        self::assertSame(0, $this->numberOf("SELECT count(*) FROM identity.app_user WHERE language = 'ar'"));
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function account(array $columns = []): void
    {
        $id = new SignInAccounts(self::getContainer())->add(self::EMAIL, $columns);
        new RoleGrants(self::getContainer())->grant($id, 'servant');
    }

    private function issued(): string
    {
        $this->jsonRequest('POST', '/api/auth/tokens', [
            'email' => self::EMAIL,
            'password' => SignInAccounts::TYPED,
            'deviceName' => " Mina's iPhone ",
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['token', 'expiresAt'], array_keys($this->responseJson()));

        return Type::of($this->responseJson()['token'] ?? null)->toString();
    }

    private function withToken(#[SensitiveParameter] string $token, string $method, string $path): void
    {
        $this->browser()->getCookieJar()->clear();
        $this->jsonRequest($method, $path, [], ['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * @param array<string, string> $input
     */
    private function console(array $input): void
    {
        $application = new Application($this->browser()->getKernel());
        $application->setAutoExit(false);
        new ApplicationTester($application)->run($input);
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
