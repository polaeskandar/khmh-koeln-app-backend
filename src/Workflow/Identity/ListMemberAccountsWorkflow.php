<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\LinkedRecords;
use App\Workflow\Identity\Model\MemberAccountList;
use App\Workflow\People\Model\DirectoryEntries;

final readonly class ListMemberAccountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MemberAccounts $accounts,
        private PersonDirectory $directory,
        private StructureLookup $structure,
        private Calendar $calendar,
        private RoleHolders $holders,
    ) {
    }

    public function __invoke(): MemberAccountList
    {
        $this->access->require(Action::Read, Resource::Users, refusal: 'accounts.adminOnly');

        $rows = $this->accounts->listed($this->holders->staff(), $this->holders->parents());
        $people = $this->directory->records(MemberAccountList::personIdsOf($rows));
        $memberships = $this->directory->listCurrentMembershipsOf($people);

        $entries = DirectoryEntries::of(
            $people,
            $memberships,
            $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
        );

        return MemberAccountList::of($rows, LinkedRecords::of($people, $entries), $this->calendar->today());
    }
}
