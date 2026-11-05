<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Privacy\Application\Service\OwnErasureRequests;
use App\Workflow\Privacy\Model\OwnErasureStatus;
use Symfony\Component\Uid\Uuid;

final readonly class ShowOwnErasureRequestWorkflow
{
    public function __construct(
        private OwnErasureRequests $requests,
    ) {
    }

    public function __invoke(string $accountId): OwnErasureStatus
    {
        return OwnErasureStatus::of($this->requests->openOf(Uuid::fromString($accountId)));
    }
}
