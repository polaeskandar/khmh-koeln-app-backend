<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class DataRequestDetails
{
    public function __construct(
        public string $kind,
        public ?string $personId,
        public ?string $accountId,
        public ReceiptDetails $receipt,
        public ?string $note,
    ) {
    }
}
