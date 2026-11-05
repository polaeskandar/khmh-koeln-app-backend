<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\ParentAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\ParentAccess;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\ParentAccessItems;
use App\Workflow\People\Model\ParentAccessList;

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
            items: ParentAccessItems::lookedUpIn(
                $this->accounts,
                $this->parentAccess,
                $this->structure,
                $this->calendar,
                $this->parentAccess->approved(self::LIMIT),
            ),
            accounts: $this->accounts->activeListed(self::LIMIT),
            today: $this->calendar->today()->toString(),
        );
    }
}
