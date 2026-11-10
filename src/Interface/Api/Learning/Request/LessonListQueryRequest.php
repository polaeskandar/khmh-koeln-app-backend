<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Context\Learning\Domain\Enum\LessonTab;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class LessonListQueryRequest
{
    public function __construct(
        #[Assert\Choice(callback: [LessonTab::class, 'values'], message: 'validation.invalidChoice')]
        public string $tab = 'upcoming',
        #[Assert\Length(max: 100, maxMessage: 'validation.tooLong')]
        public ?string $q = null,
    ) {
    }
}
