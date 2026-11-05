<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Library\Time\Client\Calendar;

final readonly class PublishedHomework
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $publishedAt,
    ) {
    }

    public static function of(HomeworkAssignment $task, Calendar $calendar): self
    {
        $publishedAt = $task->state()->publishedAt;

        return new self(
            id: $task->id()->toRfc4122(),
            status: $task->state()->status->value,
            publishedAt: $publishedAt === null ? null : $calendar->formatInstant($publishedAt),
        );
    }
}
