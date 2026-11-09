<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\ValueObject;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class IssuedCopyLink
{
    public function __construct(
        #[SensitiveParameter]
        public string $token,
        public CarbonImmutable $expiresAt,
    ) {
    }
}
