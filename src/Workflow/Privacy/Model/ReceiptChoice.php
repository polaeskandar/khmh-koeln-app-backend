<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class ReceiptChoice
{
    public function __construct(
        public string $requestedBy,
        public string $channel,
        public string $receivedOn,
    ) {
    }
}
