<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\PersonTraces;
use App\Workflow\Privacy\Model\ErasedShares;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class ErasePersonWorkflow
{
    public function __construct(
        private PersonErasures $people,
        private ErasedTotals $totals,
        private AttendanceMarks $marks,
        private HomeworkCompletions $reports,
        private PersonTraces $requests,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return PersonErasureCounts::of($this->people->erase($personId, $this->sweep(...)));
    }

    private function sweep(ErasablePerson $person): ErasedElsewhere
    {
        $sessions = $this->raisedTotals($person);
        $this->requests->forget($person->id);

        return new ErasedElsewhere($sessions, $this->marks->forget($person->id), $this->reports->forget($person->id));
    }

    private function raisedTotals(ErasablePerson $person): int
    {
        $presentAt = ErasedShares::presentAt($this->marks->ofPerson($person->id));
        $sessions = $this->totals->sessionsOf(ErasedShares::placesOf($person), $presentAt);
        $rostered = $this->people->rosteredOn($person->id, ErasedShares::expectedKeysOf($sessions));
        $shares = ErasedShares::of($sessions, $rostered, $presentAt);

        foreach ($shares as $sessionId => $share) {
            $this->totals->raise(Uuid::fromString($sessionId), $share);
        }

        return $shares->count();
    }
}
