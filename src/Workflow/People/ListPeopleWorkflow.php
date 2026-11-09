<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RosterScopes;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonLists;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\PageInfo;
use App\Workflow\People\Model\PeopleFilter;
use App\Workflow\People\Model\PeoplePage;
use App\Workflow\People\Model\PersonRow;
use App\Workflow\People\Model\Placements;

final readonly class ListPeopleWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RosterScopes $scopes,
        private PersonLists $lists,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(PeopleFilter $query): PeoplePage
    {
        $this->access->require(Action::Read, Resource::Members);

        $scope = $this->scopes->of($query->serviceId, $query->stageId);
        $filter = $this->lists->filterOf($query->status, $scope?->service->id(), $scope?->stage?->id(), $query->text);
        $people = $this->lists->page($filter, $query->page);
        $memberships = $this->lists->membershipsOf($people);

        $placements = Placements::of(
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        );

        return new PeoplePage(
            today: $filter->today->toString(),
            items: $people->map(static fn (Person $person): PersonRow => PersonRow::of(
                $person,
                $memberships->filter(static fn (Membership $held): bool => $held->person() === $person),
                $placements,
                $filter->today,
            ))->getValues(),
            pages: new PageInfo($query->page, PersonLists::PAGE_SIZE, $this->lists->count($filter)),
        );
    }
}
