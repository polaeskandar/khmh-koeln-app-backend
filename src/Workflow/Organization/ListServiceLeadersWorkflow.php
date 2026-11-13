<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Application\Service\RoleHolders;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Workflow\Identity\Model\AccountReference;

final readonly class ListServiceLeadersWorkflow
{
    private const string LEADER_ROLE = 'service_leader';

    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
        private RoleHolders $holders,
    ) {
    }

    /**
     * @return list<AccountReference>
     */
    public function __invoke(): array
    {
        $this->access->require(Action::Read, Resource::Services);

        return AccountReference::listOf($this->accounts->activeNamedByIds($this->holders->ofRoleKey(
            self::LEADER_ROLE,
        )));
    }
}
