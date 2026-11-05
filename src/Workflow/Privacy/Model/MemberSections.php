<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class MemberSections
{
    /**
     * @param list<MembershipRow> $memberships
     * @param list<AttendanceRow> $attendance
     * @param list<ReportAboutRow> $homeworkReports
     * @param list<LinkRow> $parentAccounts
     */
    public function __construct(
        public array $memberships,
        public array $attendance,
        public array $homeworkReports,
        public array $parentAccounts,
    ) {
    }
}
