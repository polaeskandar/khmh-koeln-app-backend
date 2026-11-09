<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\SessionTransitions;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Workflow\Meetings\Model\SessionState;

final readonly class OpenSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionTransitions $transitions,
        private AccountLookup $accounts,
        private ArchiveGuard $archive,
    ) {
    }

    public function __invoke(string $sessionId, string $accountId): SessionState
    {
        $this->access->require(Action::Create, Resource::Attendance);

        $session = $this->transitions->open(
            $sessionId,
            $accountId,
            ensureOpen: fn (Schedule $schedule) => $this->archive->ensureOpen(
                $schedule->roster()->serviceId,
                $schedule->roster()->stageId,
            ),
        );

        return SessionState::of($session, $this->accounts->find($session->progress()->closed->by));
    }
}
