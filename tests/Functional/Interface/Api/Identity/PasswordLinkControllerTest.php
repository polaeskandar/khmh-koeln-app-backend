<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Identity;

use App\Library\Checksum\Model\Checksum;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;

final class PasswordLinkControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;
    use MailerAssertionsTrait;

    private const string EMAIL = 'daniel@example.org';

    private const string INVALID = '{"status":422,"code":"auth.resetLinkInvalid","params":{}}';

    public function testTheEmailedLinkSetsANewPasswordAndEndsTheOpenSessions(): void
    {
        new SignInAccounts(self::getContainer())->add(self::EMAIL);
        $this->signInAs(self::EMAIL, SignInAccounts::TYPED);
        $token = $this->emailedToken();

        $this->use(['token' => $token, 'password' => SignInAccounts::MISTYPED]);
        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/me');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->jsonRequest('POST', '/api/auth/sign-in', ['email' => self::EMAIL, 'password' => SignInAccounts::TYPED]);
        $this->assertProblem(401, 'auth.invalidCredentials');

        $this->signInAs(self::EMAIL, SignInAccounts::MISTYPED);
        $this->use(['token' => $token, 'password' => SignInAccounts::TYPED]);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());

        $logs = new Logs(self::getContainer());
        self::assertSame([], $logs->containing($token));
        self::assertSame([], $logs->containing(SignInAccounts::MISTYPED));
    }

    public function testOnlyOneLinkOfAnAccountWorks(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $accountId = new SignInAccounts(self::getContainer())->add(self::EMAIL);
        $older = $this->emailedToken();
        $newer = $this->emailedToken();

        $this->use(['token' => $older, 'password' => SignInAccounts::MISTYPED]);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());

        $first = $this->storedToken($accountId);
        $second = $this->storedToken($accountId);

        $this->use(['token' => $first, 'password' => SignInAccounts::MISTYPED]);
        self::assertResponseStatusCodeSame(204);

        foreach ([$newer, $second] as $other) {
            $this->use(['token' => $other, 'password' => SignInAccounts::TYPED]);
            self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
        }
    }

    public function testDisablingAnAccountDropsItsUnusedLinks(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $token = $this->storedToken(new SignInAccounts(self::getContainer())->add(self::EMAIL));
        $application = new Application($this->browser()->getKernel());
        $application->setAutoExit(false);
        $console = new ApplicationTester($application);

        $console->run(['command' => 'app:user:disable', 'email' => self::EMAIL]);
        $console->assertCommandIsSuccessful();
        $console->run(['command' => 'app:user:enable', 'email' => self::EMAIL]);
        $console->assertCommandIsSuccessful();

        $this->use(['token' => $token, 'password' => SignInAccounts::MISTYPED]);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
    }

    /**
     * @param array<string, string|null> $account
     * @param array<string, string|null> $link
     * @param list<string> $omitted
     */
    #[DataProvider('unusableLinks')]
    public function testEveryUnusableLinkGetsTheSameAnswer(array $account, array $link, array $omitted): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $stored = $this->storedToken(new SignInAccounts(self::getContainer())->add(self::EMAIL, $account), $link);
        $body = ['token' => $stored, 'password' => SignInAccounts::MISTYPED];

        $this->use(array_diff_key($body, array_flip($omitted)));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(self::INVALID, $this->browser()->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{array<string, string|null>, array<string, string|null>, list<string>}>
     */
    public static function unusableLinks(): iterable
    {
        yield 'unknown' => [[], ['token_hash' => Checksum::sha256('another token')], []];
        yield 'missing' => [[], [], ['token']];
        yield 'used' => [[], ['used_at' => '2026-11-08 09:30:00+01'], []];
        yield 'expired' => [[], ['expires_at' => '2026-11-08 09:59:00+01'], []];
        yield 'pending account' => [['approved_at' => null], [], []];
        yield 'disabled account' => [['disabled_at' => '2026-11-08 09:30:00+01'], [], []];
    }

    public function testATooShortPasswordKeepsTheLinkUsable(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $token = $this->storedToken(new SignInAccounts(self::getContainer())->add(self::EMAIL));

        $this->use(['token' => $token, 'password' => substr(SignInAccounts::TYPED, offset: 0, length: 9)]);
        $this->assertFieldError('password', 'accounts.passwordTooShort', ['min' => 10]);

        $this->use(['token' => $token, 'password' => str_repeat('a', times: 201)]);
        $this->assertFieldError('password', 'validation.tooLong', ['max' => 200]);

        $this->use(['token' => $token, 'password' => SignInAccounts::MISTYPED]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testTheEleventhRequestFromOneClientWithinAnHourIsRefused(): void
    {
        $this->browser()->disableReboot();

        foreach (range(start: 1, end: 10) as $attempt) {
            $this->use(['token' => Uuid::v7()->toRfc4122(), 'password' => SignInAccounts::MISTYPED]);
            self::assertResponseStatusCodeSame(422, 'attempt ' . $attempt);
        }

        $this->use(['token' => Uuid::v7()->toRfc4122(), 'password' => SignInAccounts::MISTYPED]);

        $this->assertProblem(429, 'auth.tooManyAttempts');
        self::assertTrue($this->browser()->getResponse()->headers->has('Retry-After'));
    }

    /**
     * @param array<string, string> $body
     */
    private function use(array $body): void
    {
        $this->jsonRequest('POST', '/api/auth/password-links', $body);
    }

    private function emailedToken(): string
    {
        $application = new Application($this->browser()->getKernel());
        $application->setAutoExit(false);
        new ApplicationTester($application)->run(['command' => 'app:user:password-link', 'email' => self::EMAIL]);

        $messages = self::getMailerMessages();
        $email = end($messages);
        self::assertInstanceOf(Email::class, $email);
        $matches = [];
        self::assertSame(1, preg_match('/#token=([A-Za-z0-9_-]{43})/', (string) $email->getTextBody(), $matches));

        return $matches[1] ?? '';
    }

    /**
     * @param array<string, string|null> $columns
     */
    private function storedToken(string $accountId, array $columns = []): string
    {
        $token = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->insert('identity.password_token', [
                'id' => Uuid::v7()->toRfc4122(),
                'user_id' => $accountId,
                'token_hash' => Checksum::sha256($token),
                'created_at' => '2026-11-08 09:00:00+01',
                'expires_at' => '2026-11-09 09:00:00+01',
                ...$columns,
            ]);

        return $token;
    }
}
