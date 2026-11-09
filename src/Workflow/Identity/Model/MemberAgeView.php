<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Enum\AgeCheck;

final readonly class MemberAgeView
{
    public function __construct(
        public ?AgeCheck $ageCheck,
        public ?string $guardianConsentNote,
    ) {
    }

    public static function of(?string $ageCheck, ?string $guardianConsentNote): self
    {
        return new self($ageCheck === null ? null : AgeCheck::from($ageCheck), $guardianConsentNote);
    }
}
