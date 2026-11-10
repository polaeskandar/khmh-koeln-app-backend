<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ForgotPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'accounts.emailRequired', normalizer: 'trim')]
        #[Assert\Email(message: 'accounts.emailInvalid', normalizer: 'trim')]
        #[Assert\Length(max: 254, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $email = null,
    ) {
    }
}
