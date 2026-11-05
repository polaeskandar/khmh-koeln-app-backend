<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MemberApprovalRequest
{
    public function __construct(
        public ?string $personId = null,
        public ?string $ageCheck = null,
        #[Assert\Length(max: 200, maxMessage: 'validation.tooLong')]
        public ?string $guardianConsentNote = null,
    ) {
    }
}
