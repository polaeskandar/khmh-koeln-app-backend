<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MembershipTest extends TestCase
{
    public function testAMembershipIsCurrentFromItsFirstToItsLastDay(): void
    {
        $membership = self::startingOn('2026-09-01');
        $membership->endFrom(LocalDate::of('2026-11-08'));

        self::assertFalse($membership->isCurrentOn(LocalDate::of('2026-08-31')));
        self::assertTrue($membership->isCurrentOn(LocalDate::of('2026-09-01')));
        self::assertTrue($membership->isCurrentOn(LocalDate::of('2026-11-07')));
        self::assertFalse($membership->isCurrentOn(LocalDate::of('2026-11-08')));
        self::assertTrue(self::startingOn('2026-09-01')->isCurrentOn(LocalDate::of('2030-01-01')));
    }

    public function testEndingFromTodayEndsItYesterday(): void
    {
        $membership = self::startingOn('2026-09-01');

        self::assertSame(MembershipEnding::Ended, $membership->endFrom(LocalDate::of('2026-11-08')));
        self::assertSame('2026-11-07', $membership->tenure()->endsOn?->toString());
    }

    public function testAMembershipThatStartedTodayIsDeletedInstead(): void
    {
        $membership = self::startingOn('2026-11-08');

        self::assertSame(MembershipEnding::DeleteInstead, $membership->endFrom(LocalDate::of('2026-11-08')));
        self::assertNull($membership->tenure()->endsOn);
    }

    public function testAnEndedMembershipCannotEndAgain(): void
    {
        $membership = self::startingOn('2026-09-01');
        $membership->endFrom(LocalDate::of('2026-11-08'));

        $this->expectExceptionObject(new RuleViolation('people.membershipEnded'));

        $membership->endFrom(LocalDate::of('2026-11-09'));
    }

    private static function startingOn(string $date): Membership
    {
        return new Membership(
            new Person('Mina', 'Hanna', null),
            Uuid::v7(),
            stageId: null,
            startsOn: LocalDate::of($date),
        );
    }
}
