<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\MembershipWrites;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Domain\Entity\Membership;
use App\Library\Problem\Model\Ids;

final readonly class EndMembershipWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MembershipWrites $memberships,
    ) {
    }

    public function __invoke(string $membershipId): void
    {
        $this->access->require(Action::Delete, Resource::Members, refusal: 'people.removeNotAllowed');

        $id = Ids::toUuid($membershipId, notFound: 'membership');

        $this->memberships->endById($id, ensureAllowed: fn (Membership $membership) => $this->access->requireIn(
            Action::Delete,
            Resource::Members,
            PersonGroups::groupOf($membership),
            missing: 'membership',
        ));
    }
}
