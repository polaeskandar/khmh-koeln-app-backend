<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Context\Learning\Domain\Enum\LessonStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonStatusRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidChoice')]
        #[Assert\Choice(callback: [LessonStatus::class, 'values'], message: 'validation.invalidChoice')]
        public ?string $status = null,
    ) {
    }
}
