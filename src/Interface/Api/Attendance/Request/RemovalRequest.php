<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RemovalRequest
{
    public function __construct(
        #[Assert\Length(max: 200, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $reason = null,
    ) {
    }
}
