<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class DataRequestChoice
{
    public function __construct(
        public string $kind,
        public ?string $personId,
        public ?string $accountId,
        public ReceiptChoice $receipt,
        public ?string $note,
    ) {
    }
}
