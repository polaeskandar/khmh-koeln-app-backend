<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class MemberApprovalChoice
{
    public function __construct(
        public string $personId,
        public string $ageCheck,
        public ?string $guardianConsentNote,
    ) {
    }
}
