<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

final readonly class LessonOption
{
    public function __construct(
        public OptionDate $date,
        public ?LessonReference $takenBy,
    ) {
    }
}
