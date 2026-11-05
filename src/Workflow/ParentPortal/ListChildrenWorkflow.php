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
use App\Workflow\ParentPortal\Model\ChildList;
use App\Workflow\ParentPortal\Model\ChildSummary;
use App\Workflow\ParentPortal\Model\VisibleHomework;
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

        $visible = VisibleHomework::lookedUpIn(
            $this->accounts,
            $this->families,
            $this->homework,
            Uuid::fromString($viewerId),
        );

        $placements = Placements::lookedUpIn($this->structure, $visible->memberships());

        return new ChildList($visible->today->toString(), ChildSummary::listOf($visible->children, $placements));
    }
}
