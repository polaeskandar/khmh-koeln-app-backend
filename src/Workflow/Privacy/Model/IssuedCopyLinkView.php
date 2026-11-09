<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use DateTimeImmutable;
use SensitiveParameter;

final readonly class IssuedCopyLinkView
{
    public function __construct(
        #[SensitiveParameter]
        public string $url,
        public DateTimeImmutable $expiresAt,
        public ?string $emailedTo,
    ) {
    }
}
