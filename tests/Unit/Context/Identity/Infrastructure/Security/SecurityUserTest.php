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
        $user = Accounts::approved('sara@example.org');
        $securityUser = SecurityUser::of($user);

        self::assertSame($user->id()->toRfc4122(), $securityUser->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $securityUser->getRoles());
        self::assertSame($user->passwordHash(), $securityUser->getPassword());
    }

    public function testASessionHoldsNeitherTheEmailNorThePasswordHash(): void
    {
        $user = Accounts::approved('sara@example.org', passwordHash: '$2y$04$abcdefghijklmnopqrstuv');

        $stored = serialize(SecurityUser::of($user));

        self::assertStringNotContainsString('sara@example.org', $stored);
        self::assertStringNotContainsString('$2y$04$abcdefghijklmnopqrstuv', $stored);
        self::assertTrue(self::restored($stored)->isEqualTo(SecurityUser::of($user)));
    }

    public function testADifferentPasswordHashMakesItAnotherUser(): void
    {
        $user = Accounts::approved('sara@example.org', passwordHash: 'first-hash');
        $stored = self::restored(serialize(SecurityUser::of($user)));

        self::assertFalse($stored->isEqualTo(SecurityUser::of(Accounts::approved(
            'sara@example.org',
            passwordHash: 'second-hash',
        ))));
    }

    public function testASessionStoredInAnEarlierShapeEndsInsteadOfFailing(): void
    {
        $user = Accounts::approved('sara@example.org');
        $earlier = serialize(SecurityUser::of($user)) |> self::withoutStatusFingerprint(...);

        self::assertFalse(self::restored($earlier)->isEqualTo(SecurityUser::of($user)));
    }

    public function testADisabledAccountIsAnotherUser(): void
    {
        $user = Accounts::approved('sara@example.org');
        $stored = self::restored(serialize(SecurityUser::of($user)));

        $user->changeStatus($user->status()->disabledAt(new DateTimeImmutable('2026-11-09T12:00:00+01:00')));

        self::assertFalse($stored->isEqualTo(SecurityUser::of($user)));
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
