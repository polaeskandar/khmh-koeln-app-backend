<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\AttendanceMarks;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\MeetingSessions;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\RecordedMark;
use App\Context\People\Application\Service\Attendees;
use App\Library\Problem\Model\Ids;
use App\Workflow\Meetings\Model\MarkResult;
use App\Workflow\Meetings\Model\MarkView;
use Symfony\Component\Uid\Uuid;

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
        $found = $this->sessions->find(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($found);
        $this->access->requireIn(Action::Create, Resource::Attendance, $group, missing: 'session');

        $person = Ids::toUuid($personId, notFound: 'person');

        $record = fn (Session $session): RecordedMark => $this->marks->record(
            $session,
            $this->attendees->markable($person)->id(),
            Uuid::fromString($accountId),
        );

        $recorded = $this->attendees->whilePersonKept($person, fn (): RecordedMark => $this->sessions->whileMarkable(
            $found->id(),
            $record,
        ));

        $mark = $recorded->mark;

        return new MarkResult(MarkView::of($mark, $this->accounts->account($mark->recordedBy())), $recorded->created);
    }
}
