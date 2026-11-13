<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Query\GuardianRelationshipQueryInterface;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MemberRecord;
use App\Workflow\Privacy\Model\MembershipRow;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ExportMemberDataWorkflow
{
    public function __construct(
        private PersonQueryInterface $people,
        private GuardianRelationshipQueryInterface $guardians,
        private StructureLookup $structure,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(?string $personId): ?MemberData
    {
        $person = $personId === null ? null : $this->people->find(Uuid::fromString($personId));

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
        $memberships = $this->people->listMembershipsOf(new ArrayCollection([$person]));

        return MembershipRow::listOf($memberships, Placements::of(
            $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
        ));
    }

    /**
     * @return list<LinkRow>
     */
    private function parentsOf(Person $person): array
    {
        $links = $this->guardians->listForChild($person);

        return LinkRow::parentsOf($links, $this->accounts->namedByIds(LinkRow::accountIdsOf($links)));
    }
}
