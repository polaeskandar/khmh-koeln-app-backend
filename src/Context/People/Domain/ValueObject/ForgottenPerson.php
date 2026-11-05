<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

final readonly class ForgottenPerson
{
    public function __construct(
        public int $memberships,
        public int $guardianLinks,
    ) {
    }
}
