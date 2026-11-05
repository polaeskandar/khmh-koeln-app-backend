<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Workflow\Privacy\Model\DataRequestList;
use App\Workflow\Privacy\Model\DataRequestRows;

final readonly class ListDataRequestsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private DataRequests $requests,
        private PeopleExports $people,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(): DataRequestList
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $rows = DataRequestRows::of($this->requests->all(), $this->requests, $this->people, $this->accounts);

        return DataRequestList::of($rows, $this->requests->today());
    }
}
