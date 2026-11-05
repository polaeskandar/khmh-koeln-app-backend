<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\IssuedPasswordLink;
use DateTimeImmutable;

final readonly class PasswordLinkResponse
{
    public function __construct(
        public DateTimeImmutable $expiresAt,
    ) {
    }

    public static function of(IssuedPasswordLink $link): self
    {
        return new self($link->expiresAt);
    }
}
