<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Library\Time\Client\Calendar;
use App\Workflow\Privacy\Model\DataRequestList;
use App\Workflow\Privacy\Model\DataRequestRows;

final readonly class ListDataRequestsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private DataRequests $requests,
        private PersonQueryInterface $people,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(): DataRequestList
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $requests = $this->requests->listAll();

        $rows = DataRequestRows::of(
            $requests,
            $this->people->findByIds(DataRequestRows::personIdsOf($requests)),
            $this->accounts->namedByIds(DataRequestRows::accountIdsOf($requests)),
        );

        return DataRequestList::of($rows, $this->calendar->today()->toString());
    }
}
