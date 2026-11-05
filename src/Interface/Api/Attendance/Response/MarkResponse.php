<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Workflow\Attendance\Model\MarkView;
use App\Workflow\Identity\Model\AccountReference;

final readonly class MarkResponse
{
    public function __construct(
        public string $id,
        public string $sessionId,
        public string $personId,
        public string $recordedAt,
        public ?AccountReference $recordedBy,
    ) {
    }

    public static function of(MarkView $mark): self
    {
        return new self($mark->id, $mark->sessionId, $mark->personId, $mark->recordedAt, $mark->recordedBy);
    }
}
