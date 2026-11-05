<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentChildren;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\ParentPortal\Model\ChildHomeworkDetail;
use App\Workflow\ParentPortal\Model\ChildName;
use App\Workflow\ParentPortal\Model\ParentHomeworkItems;
use App\Workflow\ParentPortal\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class ShowChildHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $viewerId, string $childId, string $homeworkId): ChildHomeworkDetail
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);
        $visible = VisibleHomework::lookedUpIn($this->accounts, $this->families, $this->homework, $viewer);
        $child = $visible->requireChild(Uuid::fromString($childId));
        $shown = $visible->requireTask($child->child->id(), Uuid::fromString($homeworkId));
        $items = ParentHomeworkItems::lookedUpIn($this->structure, $this->accounts, [$shown], $viewer);

        return new ChildHomeworkDetail(
            today: $visible->today->toString(),
            child: ChildName::of($child->child),
            item: $items->itemOf($shown),
            text: HomeworkTextView::of($shown->task),
            lesson: HomeworkLessonView::of($shown->task->lesson()),
        );
    }
}
