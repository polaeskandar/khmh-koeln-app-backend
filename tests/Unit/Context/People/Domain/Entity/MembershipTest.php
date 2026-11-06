<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Time\Model\LocalDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MembershipTest extends TestCase
{
    public function testAMembershipHoldsItsPersonPlaceAndTenure(): void
    {
        $person = new Person(PersonName::of('Mina', 'Hanna', null));
        $serviceId = Uuid::v7();
        $tenure = Tenure::from(LocalDate::of('2026-09-01'));

        $membership = new Membership($person, $serviceId, stageId: null, tenure: $tenure);

        self::assertSame($person, $membership->person());
        self::assertSame($serviceId, $membership->serviceId());
        self::assertNull($membership->stageId());
        self::assertSame($tenure, $membership->tenure());
    }

    public function testAMembershipTakesTheTenureItIsGiven(): void
    {
        $membership = new Membership(
            new Person(PersonName::of('Mina', 'Hanna', null)),
            Uuid::v7(),
            stageId: null,
            tenure: Tenure::from(LocalDate::of('2026-09-01')),
        );

        $ended = Tenure::from(LocalDate::of('2026-09-01'))->endedFrom(LocalDate::of('2026-11-08'));
        self::assertNotNull($ended);

        $membership->changeTenure($ended);

        self::assertSame($ended, $membership->tenure());
    }
}
