<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentChildren;
use App\Workflow\Learning\Model\VisibleHomework;
use App\Workflow\People\Model\ChildList;
use App\Workflow\People\Model\ChildSummary;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use Symfony\Component\Uid\Uuid;

final readonly class ListChildrenWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private ParentChildren $families,
        private PupilHomework $homework,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $viewerId): ChildList
    {
        $this->access->require(Action::Read, Resource::ParentPortal);

        $viewer = Uuid::fromString($viewerId);
        $family = $this->families->familyOf($viewer);

        $visible = $this->accounts->findAccount($viewer)?->status()->isActive() === true
            ? VisibleHomework::forFamily($family, $this->homework->tasksOf(VisibleHomework::pupilsOf($family)))
            : VisibleHomework::none($family);

        $memberships = $visible->memberships();

        $placements = Placements::of(
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        );

        return new ChildList($visible->today->toString(), ChildSummary::listOf($visible->children, $placements));
    }
}
