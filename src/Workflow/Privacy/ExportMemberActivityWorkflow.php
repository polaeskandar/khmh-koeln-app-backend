<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Context\Scheduling\Application\Service\MeetingLookup;
use App\Context\Scheduling\Domain\Query\AttendanceQueryInterface;
use App\Workflow\Privacy\Model\AttendanceRow;
use App\Workflow\Privacy\Model\MemberActivity;
use App\Workflow\Privacy\Model\ReportAboutRow;
use Symfony\Component\Uid\Uuid;

final readonly class ExportMemberActivityWorkflow
{
    public function __construct(
        private PersonQueryInterface $people,
        private AttendanceQueryInterface $attendance,
        private MeetingLookup $meetings,
        private HomeworkReports $homework,
    ) {
    }

    public function __invoke(?string $personId): ?MemberActivity
    {
        $person = $personId === null ? null : $this->people->find(Uuid::fromString($personId));

        if ($person === null) {
            return null;
        }

        $marks = $this->attendance->listMarksOfPerson($person->id());

        return new MemberActivity(
            attendance: AttendanceRow::listOf(
                $marks,
                $this->meetings->listSessions(AttendanceRow::sessionIdsOf($marks)),
            ),
            homeworkReports: ReportAboutRow::listOf($this->homework->listAbout($person->id())),
        );
    }
}
