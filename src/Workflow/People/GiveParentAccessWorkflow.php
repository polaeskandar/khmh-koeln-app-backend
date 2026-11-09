<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Context\People\Application\Service\ParentGrants;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\ValueObject\AccessGrant;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\ParentAccessItem;
use App\Workflow\People\Model\ParentAccessItems;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class GiveParentAccessWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ParentAccounts $accounts,
        private ParentAccess $parentAccess,
        private ParentGrants $grants,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(
        string $accountId,
        string $personId,
        string $relationship,
        string $approvedBy,
    ): ParentAccessItem {
        $this->access->require(Action::Update, Resource::Users, refusal: 'guardians.adminOnlyGrant');

        $parent = $this->accounts->active($accountId);
        $child = $this->grants->child($personId);
        $grant = new AccessGrant($parent->id(), $parent->profile()->displayName->value, $relationship, $approvedBy);

        $link = $this->grants->grant($grant, $child, alongside: fn () => $this->accounts->giveParentRole($parent));

        $items = $this->itemsOf(new ArrayCollection([$link]));

        return $items[0] ?? throw new LogicException('A granted link has its parent and child.');
    }

    /**
     * @param ReadableCollection<int, GuardianRelationship> $links
     *
     * @return list<ParentAccessItem>
     */
    private function itemsOf(ReadableCollection $links): array
    {
        $memberships = $this->parentAccess->currentMembershipsOf(ParentAccessItems::childrenOf($links));

        return ParentAccessItems::of(
            $links,
            $this->accounts->accounts(ParentAccessItems::accountIdsOf($links)),
            $memberships,
            Placements::of(
                $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
                $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
            ),
        );
    }
}
