<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SessionDetailsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'meetings.titleRequired', normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $title = null,
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $location = null,
    ) {
    }
}
