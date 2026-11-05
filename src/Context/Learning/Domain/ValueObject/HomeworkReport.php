<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class HomeworkReport
{
    public function __construct(
        public Uuid $taskId,
        public Uuid $personId,
        public string $completedAt,
        public ?Uuid $completedBy,
        public bool $late,
    ) {
    }
}
