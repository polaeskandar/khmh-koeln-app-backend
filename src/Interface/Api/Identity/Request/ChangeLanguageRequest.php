<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangeLanguageRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidType')]
        public ?string $language = null,
    ) {
    }
}
