<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\PersonTraces;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use App\Workflow\Privacy\Model\PersonSweep;

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
        return PersonSweep::of($person, $this->totals, $this->marks, $this->reports, $this->requests);
    }
}
