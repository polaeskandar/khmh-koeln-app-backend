<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Context\People\Domain\Enum\GuardianKind;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ParentAccessRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Uuid(message: 'validation.invalidChoice')]
        public ?string $userId = null,
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Uuid(message: 'validation.invalidChoice')]
        public ?string $personId = null,
        #[Assert\Choice(callback: [GuardianKind::class, 'values'], message: 'validation.invalidChoice')]
        public string $relationship = 'parent',
    ) {
    }
}
