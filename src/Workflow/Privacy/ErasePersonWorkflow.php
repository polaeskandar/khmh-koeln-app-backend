<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\ErasedGroup;
use App\Workflow\Privacy\Model\ErasedShares;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class ErasePersonWorkflow
{
    public function __construct(
        private PersonErasures $people,
        private MeetingErasures $meetings,
        private LearningErasures $learning,
        private RequestErasures $requests,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return PersonErasureCounts::of($this->people->erase($personId, $this->sweep(...)));
    }

    private function sweep(ErasablePerson $person): ErasedElsewhere
    {
        $sessions = $this->raisedTotals($person);
        $this->requests->forgetPerson($person->id, $this->groupOf($person));

        return new ErasedElsewhere(
            $sessions,
            $this->meetings->forget($person->id),
            $this->learning->forget($person->id),
        );
    }

    private function raisedTotals(ErasablePerson $person): int
    {
        $presentAt = $this->meetings->presentAt($person->id);
        $sessions = $this->meetings->sessionsOf(ErasedShares::placesOf($person), $presentAt);
        $rostered = $this->people->rosteredOn($person->id, ErasedShares::expectedKeysOf($sessions));
        $shares = ErasedShares::of($sessions, $rostered, $presentAt);

        foreach ($shares as $sessionId => $share) {
            $this->meetings->raise(Uuid::fromString($sessionId), $share);
        }

        return $shares->count();
    }

    private function groupOf(ErasablePerson $person): ?string
    {
        $memberships = $person->memberships;

        return ErasedGroup::of($memberships, Placements::of(
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        ));
    }
}
