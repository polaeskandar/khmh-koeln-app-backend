<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\People\Application\Service\MembershipMoves;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StructureGroup;
use App\Workflow\People\Model\AddedMembership;

final readonly class MoveMembershipWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private MembershipMoves $moves,
    ) {
    }

    public function __invoke(string $membershipId, ?string $stageId): AddedMembership
    {
        $this->access->require(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');

        return $this->moves->whileMovable(
            Ids::toUuid($membershipId, notFound: 'membership'),
            fn (Membership $membership): AddedMembership => $this->moved($membership, $stageId),
        );
    }

    private function moved(Membership $membership, ?string $stageId): AddedMembership
    {
        $this->access->requireIn(
            Action::Update,
            Resource::Members,
            PersonGroups::groupOf($membership),
            missing: 'membership',
        );

        return $this->placements->whileServicesKept(
            [$membership->serviceId()->toRfc4122()],
            fn (): AddedMembership => $this->movedTo($membership, $stageId),
        );
    }

    private function movedTo(Membership $membership, ?string $stageId): AddedMembership
    {
        $chosen = $this->placements->inService($membership->serviceId()->toRfc4122(), $stageId, field: 'stageId');

        $this->access->requireChosen(
            Action::Update,
            Resource::Members,
            StructureGroup::ofPlacement($chosen),
            fieldPrefix: '',
        );

        $place = $chosen->stage?->name()->value ?? $chosen->service->name()->value;
        $target = new Placement($chosen->service->id(), $chosen->stage?->id());

        return AddedMembership::of($this->moves->move($membership, $target, $place), $chosen);
    }
}
