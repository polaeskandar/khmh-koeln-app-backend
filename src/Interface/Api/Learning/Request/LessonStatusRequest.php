<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonStatusRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidChoice')]
        #[Assert\Choice(choices: ['draft', 'ready', 'completed'], message: 'validation.invalidChoice')]
        public ?string $status = null,
    ) {
    }
}
