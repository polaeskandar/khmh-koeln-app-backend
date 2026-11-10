<?php

declare(strict_types=1);

namespace App\Workflow\Meetings;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Meetings\Application\Service\CoveredSchedules;
use App\Context\Meetings\Application\Service\SessionLookup;
use App\Context\Meetings\Domain\Query\AttendanceQueryInterface;
use App\Context\People\Application\Service\Attendees;
use App\Library\Problem\Model\Ids;
use App\Workflow\Identity\Model\AccountReferences;
use App\Workflow\Meetings\Model\Correction;
use App\Workflow\Meetings\Model\Corrections;

final readonly class ListAttendanceCorrectionsWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private SessionLookup $sessions,
        private AttendanceQueryInterface $marks,
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

        $session = $this->sessions->get(Ids::toUuid($sessionId, notFound: 'session'));
        $group = CoveredSchedules::groupOfSession($session);
        $this->access->requireIn(Action::Read, Resource::Attendance, $group, missing: 'session');
        $removed = $this->marks->listRemovedMarksOf($session->id());

        return Corrections::of(
            $removed,
            $this->attendees->peopleOf(Corrections::personIdsOf($removed)),
            AccountReferences::of($this->accounts->accounts(Corrections::accountIdsOf($removed))),
        );
    }
}
