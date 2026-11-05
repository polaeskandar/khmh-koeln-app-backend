<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use SensitiveParameter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    public function __construct(
        #[SensitiveParameter]
        #[Assert\NotBlank(message: 'accounts.currentPasswordWrong')]
        public ?string $currentPassword = null,
        #[SensitiveParameter]
        public ?string $newPassword = null,
    ) {
    }
}
