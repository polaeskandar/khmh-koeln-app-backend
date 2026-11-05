<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentChildren;
use App\Library\Problem\Exception\NotFound;
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
        $visible = $this->visibleTo($viewer);
        $childUuid = self::idOf($childId);
        $child = ($childUuid === null ? null : $visible->of($childUuid)) ?? throw NotFound::of('person');
        $taskId = self::idOf($homeworkId);

        $shown = ($taskId === null ? null : $child->tasks->task($taskId)) ?? throw NotFound::coded(
            'homework.notAvailable',
        );

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

    private static function idOf(string $id): ?Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : null;
    }
}
