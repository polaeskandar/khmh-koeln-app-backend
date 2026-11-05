<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Library\Time\Client\Calendar;

final readonly class ChangedHomework
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $publishedAt,
        public ?string $archivedAt,
    ) {
    }

    public static function of(HomeworkAssignment $task, Calendar $calendar): self
    {
        $state = $task->state();

        return new self(
            id: $task->id()->toRfc4122(),
            status: $state->status->value,
            publishedAt: $state->publishedAt === null ? null : $calendar->formatInstant($state->publishedAt),
            archivedAt: $state->archivedAt === null ? null : $calendar->formatInstant($state->archivedAt),
        );
    }
}
