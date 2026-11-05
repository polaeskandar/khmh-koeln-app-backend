<?php

declare(strict_types=1);

namespace App\Workflow\Attendance;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\RecordedMark;
use App\Context\People\Application\Service\Attendees;
use App\Workflow\Attendance\Model\MarkResult;
use App\Workflow\Attendance\Model\MarkView;

final readonly class MarkPresentWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private MeetingSessions $sessions,
        private Attendees $attendees,
        private AttendanceMarks $marks,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $sessionId, string $personId, string $accountId): MarkResult
    {
        $this->access->require(Action::Create, Resource::Attendance);

        $record = fn (Session $session): RecordedMark => $this->marks->record(
            $session,
            $this->attendees->markable($personId)->id(),
            $accountId,
        );

        $recorded = $this->attendees->whileKept($personId, fn (): RecordedMark => $this->sessions->whileMarkable(
            $sessionId,
            $record,
        ));

        $mark = $recorded->mark;

        return new MarkResult(MarkView::of($mark, $this->accounts->account($mark->recordedBy())), $recorded->created);
    }
}
