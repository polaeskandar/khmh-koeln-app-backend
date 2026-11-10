<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\Privacy\Application\Service\DataRequests;
use App\Context\Privacy\Domain\ValueObject\ReceiptDraft;
use App\Context\Privacy\Domain\ValueObject\RequestDraft;
use App\Context\Privacy\Domain\ValueObject\RequestSubject;
use App\Workflow\Privacy\Model\DataRequestDetails;
use App\Workflow\Privacy\Model\RequestSubjects;
use Symfony\Component\Uid\Uuid;

final readonly class RecordDataRequestWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private PeopleExports $people,
        private AccountLookup $accounts,
        private DataRequests $requests,
    ) {
    }

    public function __invoke(DataRequestDetails $details): string
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $receipt = $details->receipt;

        return $this->requests
            ->record(
                new RequestDraft(
                    $details->kind,
                    $this->subjectOf($details),
                    new ReceiptDraft($receipt->requestedBy, $receipt->channel, $receipt->receivedOn),
                    $details->note,
                ),
            )
            ->toRfc4122();
    }

    private function subjectOf(DataRequestDetails $details): RequestSubject
    {
        $person = $details->personId === null ? null : $this->people->person($details->personId);

        $account = $details->accountId === null
            ? null
            : $this->accounts->findAccount(Uuid::fromString($details->accountId));

        return RequestSubjects::of($person, $account);
    }
}
