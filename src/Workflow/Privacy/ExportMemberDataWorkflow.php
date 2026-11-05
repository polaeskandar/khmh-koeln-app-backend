<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MemberRecord;
use App\Workflow\Privacy\Model\MembershipRow;

final readonly class ExportMemberDataWorkflow
{
    public function __construct(
        private PeopleExports $people,
        private StructureLookup $structure,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(?string $personId): ?MemberData
    {
        $person = $personId === null ? null : $this->people->person($personId);

        return $person === null
            ? null
            : new MemberData(
                exportedAt: $this->calendar->now(),
                member: MemberRecord::of($person, $this->calendar->localDateOf($person->createdAt())),
                memberships: $this->membershipsOf($person),
                parentAccounts: $this->parentsOf($person),
            );
    }

    /**
     * @return list<MembershipRow>
     */
    private function membershipsOf(Person $person): array
    {
        $memberships = $this->people->membershipsOf($person);

        return MembershipRow::listOf($memberships, Placements::of(
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        ));
    }

    /**
     * @return list<LinkRow>
     */
    private function parentsOf(Person $person): array
    {
        $links = $this->people->linksOfChild($person);

        return LinkRow::parentsOf($links, $this->accounts->accounts(LinkRow::accountIdsOf($links)));
    }
}
