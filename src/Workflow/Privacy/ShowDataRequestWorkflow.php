<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Model\Ids;
use App\Library\Time\Client\Calendar;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\DataRequestDetail;
use App\Workflow\Privacy\Model\DataRequestRows;
use App\Workflow\Privacy\Model\MembershipRow;
use App\Workflow\Privacy\Model\RequestAccount;
use App\Workflow\Privacy\Model\RequestMember;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ShowDataRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private DataRequests $requests,
        private PersonQueryInterface $people,
        private AccountLookup $accounts,
        private StructureLookup $structure,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $requestId): DataRequestDetail
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $request = $this->requests->get(Ids::toUuid($requestId, notFound: 'request'));
        $subject = $request->subject();
        $requests = [$request];

        $accounts = $this->accounts->namedByIds(DataRequestRows::accountIdsOf($requests));

        $rows = DataRequestRows::of(
            $requests,
            $this->people->findByIds(DataRequestRows::personIdsOf($requests)),
            $accounts,
        );

        $person = $subject->personId === null ? null : $this->people->find($subject->personId);

        return new DataRequestDetail(
            $this->calendar->today()->toString(),
            $rows[0] ?? throw NotFound::of('request'),
            RequestMember::of($person, $person === null ? [] : $this->membershipsOf($person)),
            RequestAccount::of($accounts->find($subject->accountId), $accounts),
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
}
