<?php

declare(strict_types=1);

namespace App\Workflow\Organization;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Workflow\Identity\Model\AccountReference;

final readonly class ListServiceLeadersWorkflow
{
    private const string LEADER_ROLE = 'service_leader';

    public function __construct(
        private AccessCheck $access,
        private AccountLookup $accounts,
    ) {
    }

    /**
     * @return list<AccountReference>
     */
    public function __invoke(): array
    {
        $this->access->require(Action::Read, Resource::Services);

        return $this->accounts
            ->listActiveHoldersOf(self::LEADER_ROLE)
            ->map(AccountReference::of(...))
            ->getValues();
    }
}
