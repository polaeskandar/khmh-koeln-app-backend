<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Organization\Application\Service\ArchiveGuard;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\SessionLifecycle;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Problem\Model\Ids;
use App\Workflow\Scheduling\Model\SessionState;
use Symfony\Component\Uid\Uuid;

final readonly class OpenSessionWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLifecycle $lifecycle,
        private AccountLookup $accounts,
        private SessionLookup $sessions,
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

        return SessionState::of($session, $this->accounts->namedByIds([$session->progress()->closed->by]));
    }
}
