<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Library\Problem\Exception\NotFound;
use App\Workflow\Privacy\Model\DataRequestDetail;
use App\Workflow\Privacy\Model\DataRequestRows;
use App\Workflow\Privacy\Model\RequestAccount;
use App\Workflow\Privacy\Model\RequestMember;

final readonly class ShowDataRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private DataRequests $requests,
        private PeopleExports $people,
        private AccountLookup $accounts,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $requestId): DataRequestDetail
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $request = $this->requests->find($requestId);
        $subject = $request->subject();
        $rows = DataRequestRows::of([$request], $this->requests, $this->people, $this->accounts);
        $person = $subject->personId === null ? null : $this->people->person($subject->personId->toRfc4122());
        $account = $subject->accountId === null ? null : $this->accounts->account($subject->accountId);

        return new DataRequestDetail(
            $this->requests->today(),
            $rows[0] ?? throw NotFound::of('request'),
            RequestMember::of($person, $this->people, $this->structure),
            RequestAccount::of($account),
        );
    }
}
