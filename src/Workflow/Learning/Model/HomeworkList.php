<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\Organization\Model\StageSummary;

final readonly class HomeworkList
{
    /**
     * @param list<HomeworkItem> $items
     */
    public function __construct(
        public StageSummary $stage,
        public StageHomeworkFacts $facts,
        public HomeworkLessons $lessons,
        public array $items,
    ) {
    }
}
