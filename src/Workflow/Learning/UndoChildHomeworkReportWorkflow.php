<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\HomeworkReportWrites;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\People\Application\Service\ParentChildren;
use App\Workflow\Learning\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class UndoChildHomeworkReportWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private HomeworkReportWrites $reports,
    ) {
    }

    public function __invoke(string $viewerId, string $childId, string $homeworkId): void
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);

        $this->reports->whileLocked(
            $homeworkId,
            fn (HomeworkTask $task): VisibleTask => $this->undone($viewer, $childId, $task),
            fn (): never => $this->visibleTo($viewer)->missingTask($childId),
        );
    }

    private function undone(Uuid $viewer, string $childId, HomeworkTask $task): VisibleTask
    {
        $visible = $this->visibleTo($viewer);
        $shown = $visible->getTask($childId, $task->id()->toRfc4122());

        return $this->reports->undo($shown, $visible->getChild($childId)->child->id(), by: $viewer);
    }

    private function visibleTo(Uuid $viewer): VisibleHomework
    {
        $family = $this->families->ofParent($viewer);

        return $this->accounts->find($viewer)?->status()->isActive() === true
            ? VisibleHomework::forFamily($family, $this->homework->visibleTo(VisibleHomework::pupilsOf($family)))
            : VisibleHomework::none($family);
    }
}
