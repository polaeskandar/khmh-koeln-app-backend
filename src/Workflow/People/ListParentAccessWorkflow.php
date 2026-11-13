<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\ParentAccessItem;
use App\Workflow\People\Model\ParentAccessItems;
use App\Workflow\People\Model\ParentAccessList;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ListParentAccessWorkflow
{
    private const int LIMIT = 1000;

    public function __construct(
        private AccessCheck $access,
        private ParentAccounts $accounts,
        private ParentAccess $parentAccess,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): ParentAccessList
    {
        $this->access->require(Action::Read, Resource::Users);

        return new ParentAccessList(
            items: $this->itemsOf($this->parentAccess->listApproved(self::LIMIT)),
            accounts: $this->accounts->listActive(self::LIMIT),
            today: $this->calendar->today()->toString(),
        );
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
