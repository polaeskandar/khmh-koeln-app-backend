<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\MemberLinkRemovals;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class UnlinkMemberWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MemberLinkRemovals $removals,
    ) {
    }

    public function __invoke(string $accountId): void
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'accounts.adminOnly');

        $this->removals->removeFrom($accountId);
    }
}
