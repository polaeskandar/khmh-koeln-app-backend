<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountStatusTransitionsTest extends TestCase
{
    private const string CONFIRMED = '2026-10-31T10:00:00+01:00';

    private const string APPROVED = '2026-11-01T10:00:00+01:00';

    private const string DISABLED = '2026-11-02T10:00:00+01:00';

    private const string NOW = '2026-11-08T12:00:00+01:00';

    /**
     * @param callable(AccountStatus): AccountStatus $transition
     * @param array<string, string|int|null>|string $expected
     */
    #[DataProvider('transitions')]
    public function testEveryStateMeetsEveryTransition(
        AccountStatus $status,
        callable $transition,
        array|string $expected,
    ): void {
        try {
            $actual = self::described($transition($status));
        } catch (RuleViolation $refused) {
            $actual = $refused->getMessage();
        }

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{AccountStatus, callable(AccountStatus): AccountStatus, array<string, string|int|null>|string}>
     */
    public static function transitions(): iterable
    {
        $confirm = static fn (AccountStatus $status): AccountStatus => $status->emailConfirmedOn(self::at(self::NOW));
        $approve = static fn (AccountStatus $status): AccountStatus => $status->approvedOn(self::at(self::NOW));
        $disable = static fn (AccountStatus $status): AccountStatus => $status->disabledAt(self::at(self::NOW));
        $enable = static fn (AccountStatus $status): AccountStatus => $status->enabled();
        $reopen = static fn (AccountStatus $status): AccountStatus => $status->reopened();

        $awaiting = AccountStatus::pending();
        $pending = $awaiting->emailConfirmedOn(self::at(self::CONFIRMED));
        $active = $awaiting->approvedOn(self::at(self::APPROVED));
        $disabled = $active->disabledAt(self::at(self::DISABLED));
        $disabledRequest = $awaiting->disabledAt(self::at(self::DISABLED));

        yield 'awaiting confirmation, confirm' => [
            $awaiting,
            $confirm,
            self::state(null, null, 0, self::NOW, 'pending'),
        ];

        yield 'awaiting confirmation, approve' => [
            $awaiting,
            $approve,
            self::state(self::NOW, null, 0, self::NOW, 'active'),
        ];

        yield 'awaiting confirmation, disable' => [
            $awaiting,
            $disable,
            self::state(null, self::NOW, 1, null, 'disabled'),
        ];

        yield 'awaiting confirmation, enable' => [$awaiting, $enable, self::state(null, null, 0, null, 'awaiting')];
        yield 'awaiting confirmation, reopen' => [$awaiting, $reopen, self::state(null, null, 1, null, 'awaiting')];
        yield 'pending, confirm again' => [$pending, $confirm, self::state(null, null, 0, self::CONFIRMED, 'pending')];
        yield 'pending, approve' => [$pending, $approve, self::state(self::NOW, null, 0, self::CONFIRMED, 'active')];
        yield 'pending, disable' => [$pending, $disable, self::state(null, self::NOW, 1, self::CONFIRMED, 'disabled')];
        yield 'pending, enable' => [$pending, $enable, self::state(null, null, 0, self::CONFIRMED, 'pending')];
        yield 'pending, reopen' => [$pending, $reopen, self::state(null, null, 1, self::CONFIRMED, 'pending')];
        yield 'active, confirm' => [$active, $confirm, self::state(self::APPROVED, null, 0, self::APPROVED, 'active')];
        yield 'active, approve again' => [$active, $approve, self::state(self::NOW, null, 0, self::APPROVED, 'active')];

        yield 'active, disable' => [
            $active,
            $disable,
            self::state(self::APPROVED, self::NOW, 1, self::APPROVED, 'disabled'),
        ];

        yield 'active, enable' => [$active, $enable, self::state(self::APPROVED, null, 0, self::APPROVED, 'active')];
        yield 'active, reopen' => [$active, $reopen, self::state(null, null, 1, self::APPROVED, 'pending')];

        yield 'disabled, confirm' => [
            $disabled,
            $confirm,
            self::state(self::APPROVED, self::DISABLED, 1, self::APPROVED, 'disabled'),
        ];

        yield 'disabled, approve' => [$disabled, $approve, 'accounts.requestDisabled'];

        yield 'disabled, disable again' => [
            $disabled,
            $disable,
            self::state(self::APPROVED, self::DISABLED, 1, self::APPROVED, 'disabled'),
        ];

        yield 'disabled, enable' => [
            $disabled,
            $enable,
            self::state(self::APPROVED, null, 1, self::APPROVED, 'active'),
        ];

        yield 'disabled, reopen' => [
            $disabled,
            $reopen,
            self::state(null, self::DISABLED, 2, self::APPROVED, 'disabled'),
        ];

        yield 'disabled request, confirm' => [
            $disabledRequest,
            $confirm,
            self::state(null, self::DISABLED, 1, self::NOW, 'disabled'),
        ];

        yield 'disabled request, approve' => [$disabledRequest, $approve, 'accounts.requestDisabled'];
        yield 'disabled request, disable again' => [
            $disabledRequest,
            $disable,
            self::state(null, self::DISABLED, 1, null, 'disabled'),
        ];

        yield 'disabled request, enable' => [$disabledRequest, $enable, self::state(null, null, 1, null, 'awaiting')];
        yield 'disabled request, reopen' => [
            $disabledRequest,
            $reopen,
            self::state(null, self::DISABLED, 2, null, 'disabled'),
        ];
    }

    public function testRepeatingADisableOrAConfirmationKeepsTheSameValue(): void
    {
        $disabled = AccountStatus::pending()->disabledAt(self::at(self::DISABLED));
        $confirmed = AccountStatus::pending()->emailConfirmedOn(self::at(self::CONFIRMED));

        self::assertSame($disabled, $disabled->disabledAt(self::at(self::NOW)));
        self::assertNotSame($confirmed, $confirmed->emailConfirmedOn(self::at(self::NOW)));
        self::assertEquals($confirmed, $confirmed->emailConfirmedOn(self::at(self::NOW)));
    }

    /**
     * @return array<string, string|int|null>
     */
    private static function state(
        ?string $approvedAt,
        ?string $disabledAt,
        int $authVersion,
        ?string $emailConfirmedAt,
        string $state,
    ): array {
        return [
            'approvedAt' => $approvedAt,
            'disabledAt' => $disabledAt,
            'authVersion' => $authVersion,
            'emailConfirmedAt' => $emailConfirmedAt,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    private static function described(AccountStatus $status): array
    {
        return self::state(
            $status->approvedAt?->format(DATE_ATOM),
            $status->disabledAt?->format(DATE_ATOM),
            $status->authVersion,
            $status->emailConfirmedAt?->format(DATE_ATOM),
            match (true) {
                $status->disabledAt !== null => 'disabled',
                $status->isAwaitingEmailConfirmation() => 'awaiting',
                $status->isPending() => 'pending',
                default => 'active',
            },
        );
    }

    private static function at(string $instant): DateTimeImmutable
    {
        return new DateTimeImmutable($instant);
    }
}
