<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Response;

use App\Workflow\Learning\Model\PublishedHomework;
use DateTimeImmutable;

final readonly class PublishedHomeworkResponse
{
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $publishedAt,
    ) {
    }

    public static function of(PublishedHomework $homework): self
    {
        return new self($homework->id, $homework->status, $homework->publishedAt);
    }
}
