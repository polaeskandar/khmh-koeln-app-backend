<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\SessionLifecycle;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Library\Problem\Model\Ids;
use App\Workflow\Scheduling\Model\SessionState;
use Symfony\Component\Uid\Uuid;

final readonly class CancelSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLifecycle $lifecycle,
        private AccountLookup $accounts,
        private SessionLookup $sessions,
    ) {
    }

    public function __invoke(string $sessionId, string $accountId, ?string $reason): SessionState
    {
        $this->access->require(Action::Update, Resource::Sessions);
        $found = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($found);
        $this->access->requireIn(Action::Update, Resource::Sessions, $group, missing: 'session');

        $session = $this->lifecycle->cancel($found->id(), Uuid::fromString($accountId), $reason);

        return SessionState::of($session, $this->accounts->findAccount($session->progress()->closed->by));
    }
}
