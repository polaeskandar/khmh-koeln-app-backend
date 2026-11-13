<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SignInAccounts;
use App\Tests\Support\Context\Identity\Infrastructure\Security\SpyPasswordHasherFactory;
use App\Tests\Support\Logs;
use Monolog\Level;
use Monolog\LogRecord;

final class SignInFailureListenerTest extends ApiTestCase
{
    public function testAnUnknownEmailAndAWrongPasswordGetTheSameAnswer(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');

        $unknown = (string) $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => 'mina.hanna',
            'password' => SignInAccounts::TYPED,
        ])->getContent();

        $this->assertProblem(401, 'auth.invalidCredentials');

        $wrong = (string) $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => 'christine.adel',
            'password' => SignInAccounts::MISTYPED,
        ])->getContent();

        self::assertSame($unknown, $wrong);
        self::assertSame([], $this->browser()->getResponse()->headers->getCookies());
        self::assertSame([], self::loggedLines(new Logs(self::getContainer())->atOrAbove(Level::Info), 'example.org'));
    }

    public function testAnUnknownEmailCostsOneFreshPasswordCheck(): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'username' => 'mina.hanna',
            'password' => SignInAccounts::MISTYPED,
        ]);

        $spy = self::getContainer()->get(SpyPasswordHasherFactory::class);
        self::assertInstanceOf(SpyPasswordHasherFactory::class, $spy);
        $verified = $spy->verifiedHashes();
        self::assertCount(1, $verified);
        self::assertFalse($spy->getPasswordHasher(SecurityUser::class)->needsRehash($verified[0] ?? ''));
    }

    public function testAnUnknownUsernameAWrongPasswordAndADisabledAccountAnswerAlike(): void
    {
        $accounts = new SignInAccounts(self::getContainer());
        $accounts->add('christine.adel@example.org');
        $accounts->add('mina.hanna@example.org', ['disabled_at' => '2026-11-09 08:00:00+01']);
        $answers = [];

        foreach ([
            ['nobody',         SignInAccounts::TYPED],
            ['christine.adel', SignInAccounts::MISTYPED],
            ['mina.hanna',     SignInAccounts::TYPED],
        ] as [$username, $password]) {
            $this->jsonRequest('POST', '/api/auth/sign-in', ['username' => $username, 'password' => $password]);
            $this->assertProblem(401, 'auth.invalidCredentials');
            self::assertSame([], $this->browser()->getResponse()->headers->getCookies());
            $answers[] = (string) $this->browser()->getResponse()->getContent();
        }

        self::assertCount(1, array_unique($answers));
    }

    /**
     * @param list<LogRecord> $records
     *
     * @return list<string>
     */
    private static function loggedLines(array $records, string $needle): array
    {
        $lines = array_map(static fn (LogRecord $record): string => json_encode(
            $record->toArray(),
            JSON_THROW_ON_ERROR,
        ), $records);

        return array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, $needle)));
    }
}
