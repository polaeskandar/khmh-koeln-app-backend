<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class Memberships
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    public function countInService(Uuid $serviceId): int
    {
        return $this->memberships->countInService($serviceId);
    }

    public function countInStage(Uuid $stageId): int
    {
        return $this->memberships->countInStage($stageId);
    }

    public function add(string $personId, Placement $placement, string $placeName): Membership
    {
        $added = null;

        $this->people->withWriteLock(Uuid::fromString($personId), function (Person $person) use (
            $placement,
            $placeName,
            &$added,
        ): void {
            $added = $this->added($person, $placement, $placeName);
        });

        return $added ?? throw new LogicException('The membership was not added.');
    }

    public function end(string $membershipId): void
    {
        $today = $this->calendar->today();

        $this->memberships->withWriteLock(Uuid::fromString($membershipId), function (Membership $membership) use (
            $today,
        ): void {
            if ($membership->endFrom($today) === MembershipEnding::DeleteInstead) {
                $this->memberships->remove($membership);

                return;
            }

            $this->memberships->save($membership);
        });
    }

    private function added(Person $person, Placement $placement, string $placeName): Membership
    {
        if ($person->status() === PersonStatus::Inactive) {
            throw new RuleViolation('people.personInactive', ['name' => $person->name()->full()]);
        }

        $today = $this->calendar->today();

        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $today)) {
            throw AlreadyIn::of($person, $placeName);
        }

        $membership = new Membership($person, $placement->serviceId, $placement->stageId, $today);

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($person, $placeName);
        }

        return $membership;
    }
}
