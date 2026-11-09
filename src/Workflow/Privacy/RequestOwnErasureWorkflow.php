<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Privacy\Application\Service\OwnErasureRequests;
use App\Library\Problem\Exception\NotFound;
use App\Workflow\Privacy\Model\OwnErasureRequest;
use Symfony\Component\Uid\Uuid;

final readonly class RequestOwnErasureWorkflow
{
    public function __construct(
        private AccountLookup $accounts,
        private OwnErasureRequests $requests,
    ) {
    }

    public function __invoke(string $accountId, ?string $note): OwnErasureRequest
    {
        $account = $this->accounts->find(Uuid::fromString($accountId)) ?? throw NotFound::of('account');
        $requestedBy = $account->profile()->displayName->value;

        return OwnErasureRequest::of($this->requests->request($account->id(), $requestedBy, $note));
    }
}
