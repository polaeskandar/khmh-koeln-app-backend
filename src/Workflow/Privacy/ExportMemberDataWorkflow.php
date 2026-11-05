<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\People\Domain\Entity\Person;
use App\Library\Time\Client\Calendar;
use App\Workflow\Privacy\Model\AttendanceRow;
use App\Workflow\Privacy\Model\LinkRow;
use App\Workflow\Privacy\Model\MemberData;
use App\Workflow\Privacy\Model\MemberRecord;
use App\Workflow\Privacy\Model\MemberSections;
use App\Workflow\Privacy\Model\MembershipRow;
use App\Workflow\Privacy\Model\ReportAboutRow;

final readonly class ExportMemberDataWorkflow
{
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private PeopleExports $people,
        private StructureLookup $structure,
        private AttendanceMarks $attendance,
        private MeetingLookup $meetings,
        private HomeworkReports $homework,
        private AccountLookup $accounts,
        private Calendar $calendar,
    ) {
    }

    public function __invoke(string $personId): ?MemberData
    {
        $person = $this->people->person($personId);

        if ($person === null) {
            return null;
        }

        return new MemberData(
            exportedAt: $this->calendar->formatInstant($this->calendar->now()),
            member: MemberRecord::of($person, $this->calendar),
            sections: $this->sectionsOf($person),
        );
    }

    private function sectionsOf(Person $person): MemberSections
    {
        return new MemberSections(
            memberships: MembershipRow::listOf($this->people->membershipsOf($person), $this->structure),
            attendance: AttendanceRow::listOf(
                $this->attendance->ofPerson($person->id()),
                $this->meetings,
                $this->calendar,
            ),
            homeworkReports: ReportAboutRow::listOf($this->homework->about($person->id()), $this->calendar),
            parentAccounts: LinkRow::parentsOf($this->people->linksOfChild($person), $this->accounts),
        );
    }
}
