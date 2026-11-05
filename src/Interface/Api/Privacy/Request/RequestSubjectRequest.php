<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RequestSubjectRequest
{
    public function __construct(
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $personId = null,
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $accountId = null,
    ) {
    }
}
