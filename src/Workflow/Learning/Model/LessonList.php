<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class LessonList
{
    /**
     * @param array<string, int> $tabs
     * @param list<LessonRow> $items
     */
    public function __construct(
        public LessonStageView $stage,
        public string $today,
        public array $tabs,
        public LessonAbilities $abilities,
        public array $items,
    ) {
    }
}
