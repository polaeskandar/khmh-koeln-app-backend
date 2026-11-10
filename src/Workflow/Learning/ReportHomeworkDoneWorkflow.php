<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkCompletions;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\CompletionOutcome;
use App\Context\People\Application\Service\ParentChildren;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\ReportResult;
use App\Workflow\Learning\Model\VisibleHomework;
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
        $child = Ids::toUuid($childId, notFound: 'person');

        $outcome = $this->families->whileChildKept($child, fn (): CompletionOutcome => $this->reportedOnLockedTask(
            $viewer,
            $child,
            Ids::toUuidOrNull($homeworkId),
        ));

        return ReportResult::of($outcome, $this->accounts->account($outcome->completedBy), $viewer);
    }

    private function reportedOnLockedTask(Uuid $viewer, Uuid $child, ?Uuid $homeworkId): CompletionOutcome
    {
        $report = fn (HomeworkAssignment $task): CompletionOutcome => $this->reported($viewer, $child, $task);
        $reported = $this->completions->whileTaskLocked($homeworkId, $report);

        return $reported ?? $this->visibleTo($viewer)->missingTask($child);
    }

    private function reported(Uuid $viewer, Uuid $child, HomeworkAssignment $task): CompletionOutcome
    {
        $visible = $this->visibleTo($viewer);
        $shown = $visible->requireTask($child, $task->id());

        return $this->completions->report($shown, $visible->requireChild($child)->child->id(), by: $viewer);
    }

    private function visibleTo(Uuid $viewer): VisibleHomework
    {
        $family = $this->families->of($viewer);

        return $this->accounts->account($viewer)?->status()->isActive() === true
            ? VisibleHomework::forFamily($family, $this->homework->of(VisibleHomework::pupilsOf($family)))
            : VisibleHomework::none($family);
    }
}
