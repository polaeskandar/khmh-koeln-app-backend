<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

final readonly class MemberAgeView
{
    public function __construct(
        public ?string $ageCheck,
        public ?string $guardianConsentNote,
    ) {
    }
}
