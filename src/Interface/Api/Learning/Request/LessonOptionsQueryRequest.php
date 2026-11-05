<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonOptionsQueryRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $lessonId = null,
    ) {
    }
}
