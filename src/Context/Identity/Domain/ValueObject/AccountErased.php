<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

final readonly class AccountErased
{
    public function __construct(
        public int $roles,
        public int $passwordLinks,
        public int $rowsKept,
    ) {
    }
}
