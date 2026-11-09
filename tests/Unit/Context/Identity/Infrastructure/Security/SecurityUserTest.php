<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Library\Types\Model\Type;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    public function testTheAccountIsKnownByItsIdWithTheUserRoleOnly(): void
    {
        $account = Accounts::approved('sara@example.org');
        $securityUser = SecurityUser::of($account);

        self::assertSame($account->id()->toRfc4122(), $securityUser->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $securityUser->getRoles());
        self::assertSame($account->passwordHash(), $securityUser->getPassword());
    }

    public function testASessionHoldsNeitherTheEmailNorThePasswordHash(): void
    {
        $account = Accounts::approved('sara@example.org', passwordHash: '$2y$04$abcdefghijklmnopqrstuv');

        $stored = serialize(SecurityUser::of($account));

        self::assertStringNotContainsString('sara@example.org', $stored);
        self::assertStringNotContainsString('$2y$04$abcdefghijklmnopqrstuv', $stored);
        self::assertTrue(self::restored($stored)->isEqualTo(SecurityUser::of($account)));
    }

    public function testADifferentPasswordHashMakesItAnotherUser(): void
    {
        $account = Accounts::approved('sara@example.org', passwordHash: 'first-hash');
        $stored = self::restored(serialize(SecurityUser::of($account)));

        self::assertFalse($stored->isEqualTo(SecurityUser::of(Accounts::approved(
            'sara@example.org',
            passwordHash: 'second-hash',
        ))));
    }

    public function testASessionStoredInAnEarlierShapeEndsInsteadOfFailing(): void
    {
        $account = Accounts::approved('sara@example.org');
        $earlier = serialize(SecurityUser::of($account)) |> self::withoutStatusFingerprint(...);

        self::assertFalse(self::restored($earlier)->isEqualTo(SecurityUser::of($account)));
    }

    public function testADisabledAccountIsAnotherUser(): void
    {
        $account = Accounts::approved('sara@example.org');
        $stored = self::restored(serialize(SecurityUser::of($account)));

        $account->changeStatus($account->status()->disabledAt(new DateTimeImmutable('2026-11-09T12:00:00+01:00')));

        self::assertFalse($stored->isEqualTo(SecurityUser::of($account)));
    }

    private static function withoutStatusFingerprint(string $stored): string
    {
        return str_replace(search: 's:17:"statusFingerprint"', replace: 's:6:"status"', subject: $stored);
    }

    private static function restored(string $stored): SecurityUser
    {
        return Type::of(unserialize($stored))->toInstanceOf(SecurityUser::class);
    }
}
