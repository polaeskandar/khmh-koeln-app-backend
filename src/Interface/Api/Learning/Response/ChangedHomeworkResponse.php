<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\ChangedHomework;

final readonly class ChangedHomeworkResponse
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $publishedAt,
        public ?string $archivedAt,
    ) {
    }

    public static function of(ChangedHomework $homework): self
    {
        return new self($homework->id, $homework->status, $homework->publishedAt, $homework->archivedAt);
    }
}
