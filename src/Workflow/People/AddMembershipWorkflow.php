<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\People\Application\Service\Memberships;
use App\Context\People\Domain\ValueObject\Placement;
use App\Workflow\People\Model\AddedMembership;
use App\Workflow\People\Model\PlacementChoice;

final readonly class AddMembershipWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private Memberships $memberships,
    ) {
    }

    public function __invoke(string $personId, PlacementChoice $choice): AddedMembership
    {
        $this->access->require(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');

        $person = $this->memberships->personOf($personId);
        $chosen = $this->placements->of($choice->serviceId, $choice->stageId, fieldPrefix: '');
        $place = $chosen->stage?->name()->value ?? $chosen->service->name()->value;

        return AddedMembership::of(
            $this->memberships->add($person, new Placement($chosen->service->id(), $chosen->stage?->id()), $place),
            $chosen,
        );
    }
}
