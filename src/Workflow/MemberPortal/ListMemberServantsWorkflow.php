<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\MemberCheck;
use App\Workflow\MemberPortal\Model\GroupServant;
use App\Workflow\MemberPortal\Model\GroupServants;

final readonly class ListMemberServantsWorkflow
{
    public function __construct(
        private MemberCheck $member,
        private AccountLookup $accounts,
    ) {
    }

    /**
     * @return list<GroupServant>
     */
    public function __invoke(string $viewerId): array
    {
        $this->member->require($viewerId);

        return GroupServants::of(
            $this->accounts->activeHoldersOf(GroupServants::LEADER),
            $this->accounts->activeHoldersOf(GroupServants::SERVANT),
        );
    }
}
