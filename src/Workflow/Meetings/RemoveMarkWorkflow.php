<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\People\Application\Service\Attendees;

final readonly class RemoveMarkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
    ) {
    }

    public function __invoke(string $sessionId, string $personId, string $accountId, ?string $reason): void
    {
        $this->access->require(Action::Delete, Resource::Attendance);

        $this->sessions->whileMarkable($sessionId, function (Session $session) use (
            $personId,
            $accountId,
            $reason,
        ): Session {
            $this->marks->remove($session->id(), $this->attendees->find($personId)->id(), $accountId, $reason);

            return $session;
        });
    }
}
