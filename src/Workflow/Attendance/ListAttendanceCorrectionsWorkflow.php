<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Attendance\Model\Correction;
use App\Workflow\Attendance\Model\Corrections;
use App\Workflow\Identity\Model\AccountReferences;

final readonly class ListAttendanceCorrectionsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private AttendanceMarks $marks,
        private Attendees $attendees,
        private AccountLookup $accounts,
    ) {
    }

    /**
     * @return list<Correction>
     */
    public function __invoke(string $sessionId): array
    {
        $this->access->require(Action::Read, Resource::Attendance);

        $removed = $this->marks->removedOf($this->sessions->find($sessionId)->id());

        return Corrections::of(
            $removed,
            $this->attendees->peopleOf(Corrections::personIdsOf($removed)),
            AccountReferences::of($this->accounts->accounts(Corrections::accountIdsOf($removed))),
        );
    }
}
