<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\AgeCheck;

final readonly class MemberLinkView
{
    public function __construct(
        public string $personId,
        public AgeCheck $result,
        public ?string $parentAgreed,
    ) {
    }

    public static function of(User $user): ?self
    {
        $ageCheck = $user->person()->ageCheck;

        if ($ageCheck === null) {
            return null;
        }

        return new self($user->personId()->toRfc4122(), $ageCheck, $user->person()->guardianConsentNote);
    }
}
