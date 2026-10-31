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
use PHPUnit\Framework\Attributes\DataProvider;

final class SignInFailureListenerTest extends ApiTestCase
{
    public function testAnUnknownEmailAndAWrongPasswordGetTheSameAnswer(): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org');

        $unknown = (string) $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => 'mina.hanna@example.org',
            'password' => SignInAccounts::TYPED,
        ])->getContent();

        $this->assertProblem(401, 'auth.invalidCredentials');

        $wrong = (string) $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => 'christine.adel@example.org',
            'password' => SignInAccounts::MISTYPED,
        ])->getContent();

        self::assertSame($unknown, $wrong);
        self::assertSame([], $this->browser()->getResponse()->headers->getCookies());
        self::assertSame([], self::loggedLines(new Logs(self::getContainer())->atOrAbove(Level::Info), 'example.org'));
    }

    public function testAnUnknownEmailCostsOneFreshPasswordCheck(): void
    {
        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => 'mina.hanna@example.org',
            'password' => SignInAccounts::MISTYPED,
        ]);

        $spy = self::getContainer()->get(SpyPasswordHasherFactory::class);
        self::assertInstanceOf(SpyPasswordHasherFactory::class, $spy);
        $verified = $spy->verifiedHashes();
        self::assertCount(1, $verified);
        self::assertFalse($spy->getPasswordHasher(SecurityUser::class)->needsRehash($verified[0] ?? ''));
    }

    /**
     * @param array<string, string|null> $status
     */
    #[DataProvider('statuses')]
    public function testOnlyTheRightPasswordRevealsTheAccountStatus(array $status, string $code): void
    {
        new SignInAccounts(self::getContainer())->add('christine.adel@example.org', $status);

        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => 'christine.adel@example.org',
            'password' => SignInAccounts::MISTYPED,
        ]);

        $this->assertProblem(401, 'auth.invalidCredentials');

        $this->jsonRequest('POST', '/api/auth/sign-in', [
            'email' => 'christine.adel@example.org',
            'password' => SignInAccounts::TYPED,
        ]);

        $this->assertProblem(403, $code);
        self::assertSame([], $this->browser()->getResponse()->headers->getCookies());
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string}>
     */
    public static function statuses(): iterable
    {
        yield 'pending' => [['approved_at' => null], 'auth.pending'];
        yield 'disabled' => [['disabled_at' => '2026-11-09 08:00:00+01'], 'auth.disabled'];
        yield 'disabled while pending' => [
            ['approved_at' => null, 'disabled_at' => '2026-11-09 08:00:00+01'],
            'auth.disabled',
        ];
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
