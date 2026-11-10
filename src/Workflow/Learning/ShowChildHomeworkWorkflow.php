<?php

declare(strict_types=1);

namespace App\Workflow\Learning;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentChildren;
use App\Library\Problem\Model\Ids;
use App\Workflow\Learning\Model\ChildHomeworkDetail;
use App\Workflow\Learning\Model\HomeworkLessonView;
use App\Workflow\Learning\Model\HomeworkTextView;
use App\Workflow\Learning\Model\ParentHomeworkItems;
use App\Workflow\Learning\Model\VisibleHomework;
use App\Workflow\People\Model\ChildName;
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
        $visible = $this->visibleTo($viewer);
        $id = Ids::toUuid($childId, notFound: 'person');
        $child = $visible->requireChild($id);
        $shown = $visible->requireTask($id, Ids::toUuidOrNull($homeworkId));

        return new ChildHomeworkDetail(
            today: $visible->today->toString(),
            child: ChildName::of($child->child),
            item: $this->itemsOf([$shown], $viewer)->itemOf($shown),
            text: HomeworkTextView::of($shown->task),
            lesson: HomeworkLessonView::of($shown->task->lesson()),
        );
    }

    private function visibleTo(Uuid $viewer): VisibleHomework
    {
        $family = $this->families->of($viewer);

        return $this->accounts->account($viewer)?->status()->isActive() === true
            ? VisibleHomework::forFamily($family, $this->homework->of(VisibleHomework::pupilsOf($family)))
            : VisibleHomework::none($family);
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    private function itemsOf(array $tasks, Uuid $viewer): ParentHomeworkItems
    {
        return ParentHomeworkItems::of(
            $this->structure->stages(ParentHomeworkItems::stageIdsOf($tasks)),
            $this->accounts->accounts(ParentHomeworkItems::reporterIdsOf($tasks)),
            $viewer,
        );
    }
}
