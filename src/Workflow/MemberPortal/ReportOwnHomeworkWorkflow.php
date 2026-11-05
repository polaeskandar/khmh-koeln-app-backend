<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\People\Application\Service\MemberRecords;
use App\Workflow\MemberPortal\Model\OwnHomework;
use App\Workflow\ParentPortal\Model\ReportResult;
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

        return ReportResult::of($outcome, $this->accounts, $viewer);
    }

    private function reported(Uuid $personId, Uuid $viewer, HomeworkAssignment $task): CompletionOutcome
    {
        $own = OwnHomework::lookedUpIn($this->records, $this->homework, $personId);

        return $this->completions->report($own->requireTask($task->id()->toRfc4122()), $personId, by: $viewer);
    }
}
