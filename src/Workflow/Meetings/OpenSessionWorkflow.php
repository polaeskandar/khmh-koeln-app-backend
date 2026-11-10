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
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Library\Problem\Model\Ids;
use App\Workflow\Meetings\Model\SessionState;
use Symfony\Component\Uid\Uuid;

final readonly class OpenSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLifecycle $lifecycle,
        private AccountLookup $accounts,
        private MeetingSessions $sessions,
        private ArchiveGuard $archive,
    ) {
    }

    public function __invoke(string $sessionId, string $accountId): SessionState
    {
        $this->access->require(Action::Create, Resource::Attendance);
        $found = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($found);
        $this->access->requireIn(Action::Create, Resource::Attendance, $group, missing: 'session');

        $session = $this->lifecycle->open(
            $found->id(),
            Uuid::fromString($accountId),
            ensureOpen: fn (Schedule $schedule) => $this->archive->ensureOpen(
                $schedule->roster()->serviceId,
                $schedule->roster()->stageId,
            ),
        );

        return SessionState::of($session, $this->accounts->findAccount($session->progress()->closed->by));
    }
}
