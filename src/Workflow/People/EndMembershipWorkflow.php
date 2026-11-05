<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\Memberships;

final readonly class EndMembershipWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private Memberships $memberships,
    ) {
    }

    public function __invoke(string $membershipId): void
    {
        $this->access->require(Action::Delete, Resource::Members, refusal: 'people.removeNotAllowed');

        $this->memberships->end($membershipId);
    }
}
