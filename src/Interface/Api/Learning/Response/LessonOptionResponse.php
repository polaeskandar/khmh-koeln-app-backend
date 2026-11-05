<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\LessonOption;
use App\Workflow\Learning\Model\LessonReference;
use JsonSerializable;
use Override;

final readonly class LessonOptionResponse implements JsonSerializable
{
    public function __construct(
        private LessonOption $option,
    ) {
    }

    /**
     * @return array{
     *     scheduleId: string,
     *     date: string,
     *     startTime: string,
     *     title: string,
     *     state: string,
     *     takenBy: LessonReference|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $date = $this->option->date;

        return [
            'scheduleId' => $date->scheduleId,
            'date' => $date->date,
            'startTime' => $date->startTime,
            'title' => $date->title,
            'state' => $date->state,
            'takenBy' => $this->option->takenBy,
        ];
    }
}
