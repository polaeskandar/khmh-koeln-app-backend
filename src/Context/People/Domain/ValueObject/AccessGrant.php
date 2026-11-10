<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Enum\GuardianKind;
use Symfony\Component\Uid\Uuid;

final readonly class AccessGrant
{
    public function __construct(
        public Uuid $accountId,
        public string $parentName,
        public GuardianKind $relationship,
        public Uuid $approvedBy,
    ) {
    }
}
