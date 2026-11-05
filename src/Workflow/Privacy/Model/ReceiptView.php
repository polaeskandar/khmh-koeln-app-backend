<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use DateTimeImmutable;

final readonly class ReceiptView
{
    public function __construct(
        public string $requestedBy,
        public string $channel,
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
