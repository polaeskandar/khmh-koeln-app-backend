<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class ReceiptDetails
{
    public function __construct(
        public string $requestedBy,
        public string $channel,
        public string $receivedOn,
    ) {
    }
}
