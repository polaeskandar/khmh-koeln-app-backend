<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\RosterScopes;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Context\People\Application\Service\PersonLists;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatusFilter;
use App\Context\People\Domain\ValueObject\PersonFilter;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Model\Ids;
use App\Workflow\Organization\Model\StructureGroup;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\PageInfo;
use App\Workflow\People\Model\PeoplePage;
use App\Workflow\People\Model\PeopleQuery;
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

    public function __invoke(PeopleQuery $query): PeoplePage
    {
        $scope = $this->access->scope(Action::Read, Resource::Members);

        $roster = $this->scopes->of(Ids::toUuidOrNull($query->serviceId), Ids::toUuidOrNull($query->stageId));
        $this->requireShown($roster);

        $filter = $this->filterOf($query, $roster);
        $people = $this->lists->page($filter, $query->page, $scope);
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
            pages: new PageInfo($query->page, PersonLists::PAGE_SIZE, $this->lists->count($filter, $scope)),
        );
    }

    private function filterOf(PeopleQuery $query, ?ChosenPlacement $roster): PersonFilter
    {
        $status = PersonStatusFilter::tryFrom($query->status) ?? throw InvalidInput::field(
            'status',
            'validation.invalidChoice',
        );

        return $this->lists->filterOf($status, $roster?->service->id(), $roster?->stage?->id(), $query->text);
    }

    private function requireShown(?ChosenPlacement $roster): void
    {
        if ($roster?->stage !== null) {
            $this->access->requireIn(
                Action::Read,
                Resource::Members,
                StructureGroup::ofPlacement($roster),
                missing: 'stage',
            );

            return;
        }

        if ($roster === null) {
            return;
        }

        $this->access->requireReaching(Action::Read, Resource::Members, $roster->service->id(), missing: 'service');
    }
}
