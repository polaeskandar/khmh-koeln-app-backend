<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SessionDetailsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'meetings.titleRequired', normalizer: 'trim')]
        #[Assert\Length(max: 120, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $title = null,
        #[Assert\Length(max: 120, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $location = null,
    ) {
    }
}
