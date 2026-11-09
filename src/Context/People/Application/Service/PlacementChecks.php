<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\ValueObject\AlreadyIn;
use App\Context\People\Domain\ValueObject\AlreadyInService;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\RosterNames;
use App\Library\Time\Model\LocalDate;

final readonly class PlacementChecks
{
    public function __construct(
        private MembershipRepositoryInterface $memberships,
    ) {
    }

    public function ensureFree(Person $person, Placement $placement, RosterNames $names, LocalDate $on): void
    {
        if ($this->memberships->hasCurrent($person, $placement->serviceId, $placement->stageId, $on)) {
            throw AlreadyIn::of($person, $names->of($placement->stageId));
        }

        $otherLevel = $this->memberships->findAtOtherLevel($person, $placement, $on);

        if ($otherLevel !== null) {
            throw AlreadyInService::of($person, $names->of($otherLevel->stageId()));
        }
    }
}
