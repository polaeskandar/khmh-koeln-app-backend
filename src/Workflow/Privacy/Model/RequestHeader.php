<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\Enum\RequestKind;
use App\Context\Privacy\Domain\Enum\RequestStatus;

final readonly class RequestHeader
{
    public function __construct(
        public string $id,
        public RequestKind $kind,
        public RequestStatus $status,
        public string $dueOn,
        public ?string $note,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Open;
    }
}
