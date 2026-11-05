<?php

declare(strict_types=1);

namespace App\Interface\Api\Auth\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ForgotPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'accounts.emailRequired', normalizer: 'trim')]
        #[Assert\Email(message: 'accounts.emailInvalid', normalizer: 'trim')]
        #[Assert\Length(max: 254, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $email = null,
    ) {
    }
}
