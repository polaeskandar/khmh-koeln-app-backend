<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
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
