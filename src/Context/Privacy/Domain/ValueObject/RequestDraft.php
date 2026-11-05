<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

final readonly class RequestDraft
{
    public function __construct(
        public string $kind,
        public RequestSubject $subject,
        public ReceiptDraft $receipt,
        public ?string $note,
    ) {
    }
}
