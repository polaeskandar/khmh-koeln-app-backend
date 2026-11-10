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
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\ParentChildren;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class UndoHomeworkReportWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private HomeworkCompletions $completions,
    ) {
    }

    public function __invoke(string $viewerId, string $childId, string $homeworkId): void
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);
        $child = Ids::toUuid($childId, notFound: 'person');

        $undone = $this->completions->whileTaskLocked(
            Ids::toUuidOrNull($homeworkId),
            fn (HomeworkAssignment $task): VisibleTask => $this->undone($viewer, $child, $task),
        );

        if ($undone === null) {
            $this->visibleTo($viewer)->missingTask($child);
        }
    }

    private function undone(Uuid $viewer, Uuid $child, HomeworkAssignment $task): VisibleTask
    {
        $visible = $this->visibleTo($viewer);
        $shown = $visible->requireTask($child, $task->id());

        return $this->completions->undo($shown, $visible->requireChild($child)->child->id(), by: $viewer);
    }

    private function visibleTo(Uuid $viewer): VisibleHomework
    {
        $family = $this->families->of($viewer);

        return $this->accounts->account($viewer)?->status()->isActive() === true
            ? VisibleHomework::forFamily($family, $this->homework->of(VisibleHomework::pupilsOf($family)))
            : VisibleHomework::none($family);
    }
}
