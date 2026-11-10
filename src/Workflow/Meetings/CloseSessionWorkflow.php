<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Application\Service\SessionLifecycle;
use App\Workflow\Meetings\Model\SessionState;

final readonly class CloseSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLifecycle $lifecycle,
        private AccountLookup $accounts,
        private MeetingSessions $sessions,
    ) {
    }

    public function __invoke(string $sessionId, string $accountId): SessionState
    {
        $this->access->require(Action::Create, Resource::Attendance);
        $group = CoveredSchedules::groupOfSession($this->sessions->find($sessionId));
        $this->access->requireIn(Action::Create, Resource::Attendance, $group, missing: 'session');

        $session = $this->lifecycle->close($sessionId, $accountId);

        return SessionState::of($session, $this->accounts->account($session->progress()->closed->by));
    }
}
