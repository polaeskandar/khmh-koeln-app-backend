<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Meetings\Application\Service\ErasedTotals;
use App\Context\People\Application\Service\PersonErasures;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use App\Workflow\Privacy\Model\PersonSweep;

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
        return $this->transactions->run(fn (): PersonErasureCounts => PersonSweep::of(
            $personId,
            $this->people,
            $this->totals,
            $this->marks,
            $this->reports,
        ));
    }
}
