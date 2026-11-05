<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\StaffAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\StaffAccount;
use App\Workflow\Identity\Model\StaffAccountList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ListStaffAccountsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private StaffAccounts $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): StaffAccountList
    {
        $this->access->require(Action::Read, Resource::Users);

        return new StaffAccountList(
            new ArrayCollection($this->accounts->listed())->map(StaffAccount::of(...))->getValues(),
            $this->calendar->today()->toString(),
        );
    }
}
