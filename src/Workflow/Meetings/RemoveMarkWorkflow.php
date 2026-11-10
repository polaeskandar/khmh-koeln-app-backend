<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceWrites;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\SessionLookup;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\People\Application\Service\Attendees;
use App\Library\Problem\Model\Ids;
use Symfony\Component\Uid\Uuid;

final readonly class RemoveMarkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private Attendees $attendees,
        private AttendanceWrites $marks,
    ) {
    }

    public function __invoke(string $sessionId, string $personId, string $accountId, ?string $reason): void
    {
        $this->access->require(Action::Delete, Resource::Attendance);
        $found = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($found);
        $this->access->requireIn(Action::Delete, Resource::Attendance, $group, missing: 'session');

        $this->marks->whileMarkable($found->id(), function (Session $session) use (
            $personId,
            $accountId,
            $reason,
        ): Session {
            $person = $this->attendees->get(Ids::toUuid($personId, notFound: 'person'));
            $this->marks->remove($session->id(), $person->id(), Uuid::fromString($accountId), $reason);

            return $session;
        });
    }
}
