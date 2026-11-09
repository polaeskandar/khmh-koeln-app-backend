<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\IssuedCopyLinkView;
use DateTimeImmutable;

final readonly class CopyLinkResponse
{
    public function __construct(
        public string $url,
        public DateTimeImmutable $expiresAt,
        public ?string $emailedTo,
    ) {
    }

    public static function of(IssuedCopyLinkView $issued): self
    {
        return new self($issued->url, $issued->expiresAt, $issued->emailedTo);
    }
}
