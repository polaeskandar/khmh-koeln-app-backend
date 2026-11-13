<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountStatusTransitionsTest extends TestCase
{
    private const string DISABLED = '2026-11-02T10:00:00+01:00';

    private const string NOW = '2026-11-08T12:00:00+01:00';

    /**
     * @param callable(AccountStatus): AccountStatus $transition
     * @param array<string, string|int|bool|null> $expected
     */
    #[DataProvider('transitions')]
    public function testEveryStateMeetsEveryTransition(
        AccountStatus $status,
        callable $transition,
        array $expected,
    ): void {
        self::assertSame($expected, self::described($transition($status)));
    }

    /**
     * @return iterable<string, array{AccountStatus, callable(AccountStatus): AccountStatus, array<string, string|int|bool|null>}>
     */
    public static function transitions(): iterable
    {
        $disable = static fn (AccountStatus $status): AccountStatus => $status->disabledAt(self::at(self::NOW));
        $enable = static fn (AccountStatus $status): AccountStatus => $status->enabled();

        $active = AccountStatus::active();
        $disabled = $active->disabledAt(self::at(self::DISABLED));

        yield 'active, disable' => [$active, $disable, self::state(self::NOW, 1, active: false)];
        yield 'active, enable' => [$active, $enable, self::state(null, 0, active: true)];
        yield 'disabled, disable again' => [$disabled, $disable, self::state(self::DISABLED, 1, active: false)];
        yield 'disabled, enable' => [$disabled, $enable, self::state(null, 1, active: true)];
    }

    public function testRepeatingADisableKeepsTheSameValue(): void
    {
        $disabled = AccountStatus::active()->disabledAt(self::at(self::DISABLED));

        self::assertSame($disabled, $disabled->disabledAt(self::at(self::NOW)));
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private static function state(?string $disabledAt, int $authVersion, bool $active): array
    {
        return ['disabledAt' => $disabledAt, 'authVersion' => $authVersion, 'active' => $active];
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    private static function described(AccountStatus $status): array
    {
        return self::state($status->disabledAt?->format(DATE_ATOM), $status->authVersion, $status->isActive());
    }

    private static function at(string $instant): DateTimeImmutable
    {
        return new DateTimeImmutable($instant);
    }
}
