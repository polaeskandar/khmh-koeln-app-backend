<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleAssignments;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Context\People\Application\Service\ParentGrants;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Context\People\Domain\Enum\GuardianKind;
use App\Context\People\Domain\ValueObject\AccessGrant;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\ParentAccessItem;
use App\Workflow\People\Model\ParentAccessItems;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class GiveParentAccessWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private ParentAccounts $accounts,
        private ParentAccess $parentAccess,
        private ParentGrants $grants,
        private StructureLookup $structure,
        private RoleAssignments $roles,
    ) {
    }

    public function __invoke(
        string $userId,
        string $personId,
        string $relationship,
        string $approvedBy,
    ): ParentAccessItem {
        $this->access->require(Action::Update, Resource::Users, refusal: 'guardians.adminOnlyGrant');

        $parent = $this->accounts->getActive(Ids::toUuid($userId, notFound: 'user'));
        $child = $this->grants->getChild(Ids::toUuid($personId, notFound: 'person'));

        $grant = new AccessGrant(
            $parent->id(),
            $this->accounts->nameOf($parent),
            GuardianKind::tryFrom($relationship) ?? throw InvalidInput::field(
                'relationship',
                'validation.invalidChoice',
            ),
            Uuid::fromString($approvedBy),
        );

        $link = $this->grants->grant($grant, $child, alongside: fn () => $this->roles->ensureParentRole($parent->id()));

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
        $memberships = $this->parentAccess->listCurrentMembershipsOf(ParentAccessItems::childrenOf($links));

        return ParentAccessItems::of(
            $links,
            $this->accounts->namedByIds(ParentAccessItems::accountIdsOf($links)),
            $memberships,
            Placements::of(
                $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
                $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
            ),
        );
    }
}
