<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

final readonly class ReceiptDraft
{
    public function __construct(
        public string $requestedBy,
        public string $channel,
        public string $receivedOn,
    ) {
    }
}
