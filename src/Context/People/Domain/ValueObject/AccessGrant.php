<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class AccessGrant
{
    public function __construct(
        public Uuid $accountId,
        public string $parentName,
        public string $relationship,
        public string $approvedBy,
    ) {
    }
}
