<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\Enum\RequestChannel;
use DateTimeImmutable;

final readonly class ReceiptView
{
    public function __construct(
        public string $requestedBy,
        public RequestChannel $channel,
        public DateTimeImmutable $receivedAt,
    ) {
    }
}
