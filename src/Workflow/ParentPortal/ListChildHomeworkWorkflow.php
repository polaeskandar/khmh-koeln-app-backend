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
use App\Library\Problem\Exception\NotFound;
use App\Workflow\ParentPortal\Model\ChildHomeworkList;
use App\Workflow\ParentPortal\Model\ParentHomeworkItems;
use App\Workflow\ParentPortal\Model\ParentHomeworkOrder;
use App\Workflow\ParentPortal\Model\VisibleHomework;
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
        $visible = VisibleHomework::lookedUpIn($this->accounts, $this->families, $this->homework, $viewer);
        $child = $visible->of(Uuid::fromString($childId)) ?? throw NotFound::of('person');
        $shown = ParentHomeworkOrder::shown($child->tasks, $state);
        $items = ParentHomeworkItems::lookedUpIn($this->structure, $this->accounts, $shown, $viewer);

        return new ChildHomeworkList($visible->today->toString(), $items->listOf($shown));
    }
}
