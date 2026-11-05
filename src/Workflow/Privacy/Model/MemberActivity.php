<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class MemberActivity
{
    /**
     * @param list<AttendanceRow> $attendance
     * @param list<ReportAboutRow> $homeworkReports
     */
    public function __construct(
        public array $attendance,
        public array $homeworkReports,
    ) {
    }
}
