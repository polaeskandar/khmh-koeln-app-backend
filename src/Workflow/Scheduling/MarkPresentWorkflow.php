<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\People\Application\Service\Attendees;
use App\Context\Scheduling\Application\Service\AttendanceWrites;
use App\Context\Scheduling\Application\Service\CoveredSchedules;
use App\Context\Scheduling\Application\Service\SessionLookup;
use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\RecordedMark;
use App\Library\Problem\Model\Ids;
use App\Workflow\Scheduling\Model\MarkResult;
use App\Workflow\Scheduling\Model\MarkView;
use Symfony\Component\Uid\Uuid;

final readonly class MarkPresentWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private Attendees $attendees,
        private AttendanceWrites $marks,
        private AccountLookup $accounts,
    ) {
    }

    public function __invoke(string $sessionId, string $personId, string $accountId): MarkResult
    {
        $this->access->require(Action::Create, Resource::Attendance);
        $found = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($found);
        $this->access->requireIn(Action::Create, Resource::Attendance, $group, missing: 'session');

        $person = Ids::toUuid($personId, notFound: 'person');

        $record = fn (Session $session): RecordedMark => $this->marks->record(
            $session,
            $this->attendees->markable($person)->id(),
            Uuid::fromString($accountId),
        );

        $recorded = $this->attendees->whilePersonKept($person, fn (): RecordedMark => $this->marks->whileMarkable(
            $found->id(),
            $record,
        ));

        $mark = $recorded->mark;

        return new MarkResult(
            MarkView::of($mark, $this->accounts->namedByIds([$mark->recordedBy()])),
            $recorded->created,
        );
    }
}
