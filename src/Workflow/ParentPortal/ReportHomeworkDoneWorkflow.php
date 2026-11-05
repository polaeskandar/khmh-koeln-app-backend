<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\People\Application\Service\ParentChildren;
use App\Workflow\ParentPortal\Model\ReportResult;
use App\Workflow\ParentPortal\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class ReportHomeworkDoneWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private HomeworkCompletions $completions,
    ) {
    }

    public function __invoke(string $viewerId, string $childId, string $homeworkId): ReportResult
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);
        $child = Uuid::fromString($childId);

        $outcome = $this->completions->whileLocked(
            $homeworkId,
            fn (HomeworkAssignment $task): CompletionOutcome => $this->reported($viewer, $child, $task),
            fn (): never => $this->visibleTo($viewer)->missingTask($child),
        );

        return ReportResult::of($outcome, $this->accounts, $viewer);
    }

    private function reported(Uuid $viewer, Uuid $child, HomeworkAssignment $task): CompletionOutcome
    {
        $visible = $this->visibleTo($viewer)->requireTask($child, $task->id());

        return $this->completions->report($visible, $child, by: $viewer);
    }

    private function visibleTo(Uuid $viewer): VisibleHomework
    {
        return VisibleHomework::lookedUpIn($this->accounts, $this->families, $this->homework, $viewer);
    }
}
