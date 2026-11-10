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
use App\Workflow\Learning\Model\ChildHomeworkList;
use App\Workflow\Learning\Model\ParentHomeworkItems;
use App\Workflow\Learning\Model\ParentHomeworkOrder;
use App\Workflow\Learning\Model\VisibleHomework;
use Symfony\Component\Uid\Uuid;

final readonly class ListChildHomeworkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $viewerId, string $childId, string $state): ChildHomeworkList
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);
        $visible = $this->visibleTo($viewer);
        $child = $visible->requireChild(Ids::toUuid($childId, notFound: 'person'));
        $shown = ParentHomeworkOrder::shown($child->tasks->tasks, $state);

        return new ChildHomeworkList($visible->today->toString(), $this->itemsOf($shown, $viewer)->listOf($shown));
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
