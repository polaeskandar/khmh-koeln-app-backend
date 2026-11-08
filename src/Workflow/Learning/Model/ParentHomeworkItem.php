<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class ParentHomeworkItem
{
    public function __construct(
        public ParentTaskView $task,
        public ParentReportView $report,
    ) {
    }
}
