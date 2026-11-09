<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SessionTransitions;
use App\Workflow\Meetings\Model\SessionState;

final readonly class CancelSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionTransitions $transitions,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $sessionId, string $accountId, ?string $reason): SessionState
    {
        $this->access->require(Action::Update, Resource::Sessions);

        $session = $this->transitions->cancel($sessionId, $accountId, $reason);

        return SessionState::of($session, $this->accounts->find($session->progress()->closed->by));
    }
}
