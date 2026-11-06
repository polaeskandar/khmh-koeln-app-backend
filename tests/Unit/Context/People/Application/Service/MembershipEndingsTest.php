<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Application\Service;

use App\Context\People\Application\Service\MembershipEndings;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MembershipEndingsTest extends TestCase
{
    public function testEndingFromTodayEndsItYesterday(): void
    {
        $membership = self::startingOn('2026-09-01');

        self::assertSame(MembershipEnding::Ended, MembershipEndings::end($membership, LocalDate::of('2026-11-08')));

        self::assertSame('2026-11-07', $membership->tenure()->endsOn?->toString());
    }

    public function testAMembershipThatStartedTodayIsDeletedInstead(): void
    {
        $membership = self::startingOn('2026-11-08');

        self::assertSame(MembershipEnding::DeleteInstead, MembershipEndings::end(
            $membership,
            LocalDate::of('2026-11-08'),
        ));

        self::assertNull($membership->tenure()->endsOn);
    }

    public function testAnEndedMembershipCannotEndAgain(): void
    {
        $membership = self::startingOn('2026-09-01');
        MembershipEndings::end($membership, LocalDate::of('2026-11-08'));

        $this->expectExceptionObject(new RuleViolation('people.membershipEnded'));

        MembershipEndings::end($membership, LocalDate::of('2026-11-09'));
    }

    private static function startingOn(string $date): Membership
    {
        return new Membership(
            new Person(PersonName::of('Mina', 'Hanna', null)),
            Uuid::v7(),
            stageId: null,
            tenure: Tenure::from(LocalDate::of($date)),
        );
    }
}
