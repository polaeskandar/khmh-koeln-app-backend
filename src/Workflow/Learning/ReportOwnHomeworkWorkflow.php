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
        $personId = $this->member->require($viewerId);
        $viewer = Uuid::fromString($viewerId);

        $outcome = $this->records->whileKept($personId, fn (): CompletionOutcome => $this->completions->whileLocked(
            $homeworkId,
            fn (HomeworkAssignment $task): CompletionOutcome => $this->reported($personId, $viewer, $task),
            OwnHomework::notAvailable(...),
        ));

        return ReportResult::of($outcome, $this->accounts->account($outcome->completedBy), $viewer);
    }

    private function reported(Uuid $personId, Uuid $viewer, HomeworkAssignment $task): CompletionOutcome
    {
        $record = $this->records->ownRecord($personId);
        $own = OwnHomework::of($record, $this->homework->of(OwnHomework::pupilsOf($record)));

        return $this->completions->report($own->requireTask($task->id()->toRfc4122()), $personId, by: $viewer);
    }
}
