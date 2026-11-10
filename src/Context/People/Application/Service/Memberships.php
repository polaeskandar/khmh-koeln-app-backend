<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\AlreadyInService;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\RosterNames;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    public function personOf(Uuid $personId): Person
    {
        return $this->people->find($personId) ?? throw NotFound::of('person');
    }

    public function add(Person $person, Placement $placement, RosterNames $names): Membership
    {
        return $this->people->withWriteLock($person->id(), fn (Person $locked): Membership => $this->added(
            $locked,
            $placement,
            $names,
        ));
    }

    private function added(Person $person, Placement $placement, RosterNames $names): Membership
    {
        if ($person->status() !== PersonStatus::Active) {
            throw new RuleViolation('people.personInactive', ['name' => self::nameOf($person)]);
        }

        $today = $this->calendar->today();
        $this->ensureFree($person, $placement, $names, $today);
        $membership = new Membership($person, $placement->serviceId, $placement->stageId, Tenure::from($today));

        try {
            $this->memberships->save($membership);
        } catch (UniqueConstraintViolationException) {
            throw AlreadyIn::of($person, $names->of($placement->stageId));
        }

        return $membership;
    }

    private function ensureFree(Person $person, Placement $placement, RosterNames $names, LocalDate $on): void
    {
        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $on)) {
            throw AlreadyIn::of($person, $names->of($placement->stageId));
        }

        $otherLevel = $this->memberships->findAtOtherLevel($person, $placement, $on);

        if ($otherLevel !== null) {
            throw AlreadyInService::of($person, $names->of($otherLevel->stageId()));
        }
    }

    private static function nameOf(Person $person): string
    {
        return $person->name()->fullName();
    }
}
