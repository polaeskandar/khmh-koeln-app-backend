<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class AccountData
{
    /**
     * @param list<LinkRow> $children
     * @param list<ReportByRow> $homeworkReports
     */
    public function __construct(
        public string $exportedAt,
        public AccountRecord $account,
        public array $children,
        public array $homeworkReports,
    ) {
    }
}
