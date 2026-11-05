<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\ErasedShares;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class ErasePersonWorkflow
{
    public function __construct(
        private Transactions $transactions,
        private PersonErasures $people,
        private ErasedTotals $totals,
        private AttendanceMarks $marks,
        private HomeworkCompletions $reports,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return $this->transactions->run(fn (): PersonErasureCounts => $this->erased($personId));
    }

    private function erased(string $personId): PersonErasureCounts
    {
        $person = $this->people->erasable($personId);
        $sessions = $this->raisedTotals($person);
        $marks = $this->marks->forget($person->id);
        $reports = $this->reports->forget($person->id);
        $forgotten = $this->people->forget($person->id);

        return new PersonErasureCounts($forgotten->memberships, $marks, $reports, $forgotten->guardianLinks, $sessions);
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
