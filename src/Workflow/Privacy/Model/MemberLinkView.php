<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\ValueObject\MemberLink;

final readonly class MemberLinkView
{
    public function __construct(
        public string $personId,
        public string $result,
        public ?string $parentAgreed,
    ) {
    }

    public static function of(MemberLink $link): ?self
    {
        $personId = $link->personId;
        $ageCheck = $link->ageCheck;

        if ($personId === null || $ageCheck === null) {
            return null;
        }

        return new self($personId->toRfc4122(), $ageCheck->value, $link->guardianConsentNote);
    }
}
