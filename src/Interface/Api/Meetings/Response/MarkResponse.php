<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Response;

use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Meetings\Model\MarkView;
use DateTimeImmutable;

final readonly class MarkResponse
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $personId,
        public DateTimeImmutable $recordedAt,
        public ?AccountReference $recordedBy,
    ) {
    }

    public static function of(MarkView $mark): self
    {
        return new self($mark->id, $mark->sessionId, $mark->personId, $mark->recordedAt, $mark->recordedBy);
    }
}
