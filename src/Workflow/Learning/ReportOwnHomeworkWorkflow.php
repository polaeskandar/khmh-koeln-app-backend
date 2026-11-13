<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\People\Application\Service\MemberRecords;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\OwnHomework;
use App\Workflow\Learning\Model\ReportResult;
use Symfony\Component\Uid\Uuid;

final readonly class ReportOwnHomeworkWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private AccountLookup $accounts,
        private MemberRecords $records,
        private PupilHomework $homework,
        private HomeworkCompletions $completions,
    ) {
    }

    public function __invoke(string $viewerId, string $homeworkId): ReportResult
    {
        $viewer = Uuid::fromString($viewerId);
        $personId = $this->member->require($viewer);

        $outcome = $this->records->whilePersonKept($personId, fn (): CompletionOutcome => $this->reportedOnLockedTask(
            $personId,
            $viewer,
            Ids::toUuidOrNull($homeworkId),
        ));

        return ReportResult::of($outcome, $this->accounts->namedByIds([$outcome->completedBy]), $viewer);
    }

    private function reportedOnLockedTask(Uuid $personId, Uuid $viewer, ?Uuid $homeworkId): CompletionOutcome
    {
        $report = fn (HomeworkAssignment $task): CompletionOutcome => $this->reported($personId, $viewer, $task);
        $reported = $this->completions->whileTaskLocked($homeworkId, $report);

        return $reported ?? OwnHomework::notAvailable();
    }

    private function reported(Uuid $personId, Uuid $viewer, HomeworkAssignment $task): CompletionOutcome
    {
        $record = $this->records->getOwnRecord($personId);
        $own = OwnHomework::of($record, $this->homework->listTasksOf(OwnHomework::pupilsOf($record)));

        return $this->completions->report($own->requireTask($task->id()), $personId, by: $viewer);
    }
}
