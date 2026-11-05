<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

final readonly class RequestHeader
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $status,
        public string $dueOn,
        public ?string $note,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
