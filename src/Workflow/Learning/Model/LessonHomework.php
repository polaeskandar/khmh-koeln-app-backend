<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\HomeworkCounts;

final readonly class LessonHomework
{
    public function __construct(
        public int $total,
        public int $published,
        public int $drafts,
    ) {
    }

    public static function of(HomeworkCounts $counts): self
    {
        return new self($counts->total(), $counts->published, $counts->drafts);
    }
}
