<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\MemberAccountList;

final readonly class ListMemberAccountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MemberAccounts $accounts,
        private PersonDirectory $directory,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): MemberAccountList
    {
        $this->access->require(Action::Read, Resource::Users, refusal: 'accounts.adminOnly');

        return MemberAccountList::lookedUpIn($this->accounts, $this->directory, $this->structure, $this->calendar);
    }
}
