<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\ServiceStages;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Memberships;
use App\Context\People\Application\Service\PersonGroups;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\Placement;
use App\Context\People\Domain\ValueObject\RosterNames;
use App\Library\Collections\Model\Collections;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StructureGroup;
use App\Workflow\People\Model\AddedMembership;
use App\Workflow\People\Model\PlacementChoice;

final readonly class AddMembershipWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PlacementChoices $placements,
        private ServiceStages $stages,
        private Memberships $memberships,
        private PersonGroups $groups,
    ) {
    }

    public function __invoke(string $personId, PlacementChoice $choice): AddedMembership
    {
        $scope = $this->access->scope(Action::Update, Resource::Members, refusal: 'people.changeNotAllowed');

        $person = $this->memberships->personOf(Ids::toUuid($personId, notFound: 'person'));
        $this->groups->requireVisible($person->id(), $scope);

        return $this->placements->whileKept([$choice->serviceId], fn (): AddedMembership => $this->added(
            $person,
            $choice,
        ));
    }

    private function added(Person $person, PlacementChoice $choice): AddedMembership
    {
        $chosen = $this->placements->of($choice->serviceId, $choice->stageId, fieldPrefix: '');

        $this->access->requireChosen(
            Action::Update,
            Resource::Members,
            StructureGroup::ofPlacement($chosen),
            fieldPrefix: '',
        );

        $placement = new Placement($chosen->service->id(), $chosen->stage?->id());

        return AddedMembership::of(
            $this->memberships->add($person, $placement, $this->rosterNamesOf($chosen->service)),
            $chosen,
        );
    }

    private function rosterNamesOf(Service $service): RosterNames
    {
        $stages = Collections::firstPerKey($this->stages->ofService($service), keyOf: self::stageId(...));

        return new RosterNames($service->name()->value, $stages->map(self::stageName(...)));
    }

    /**
     * @return non-empty-string
     */
    private static function stageId(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }

    private static function stageName(Stage $stage): string
    {
        return $stage->name()->value;
    }
}
