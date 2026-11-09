<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Workflow\Learning\Model\WrittenHomework;

final readonly class WrittenHomeworkResponse
{
    public function __construct(
        public string $id,
        public HomeworkStatus $status,
        public int $version,
    ) {
    }

    public static function of(WrittenHomework $homework): self
    {
        return new self($homework->id, $homework->status, $homework->version);
    }
}
