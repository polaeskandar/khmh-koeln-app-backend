<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class LessonList
{
    /**
     * @param array<string, int> $tabs
     * @param list<AccountReference> $servants
     * @param list<LessonRow> $items
     */
    public function __construct(
        public LessonStageView $stage,
        public string $today,
        public array $tabs,
        public array $servants,
        public array $items,
    ) {
    }
}
