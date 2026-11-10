<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use App\Context\Learning\Domain\Enum\HomeworkFilter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChildHomeworkQueryRequest
{
    public function __construct(
        #[Assert\Choice(callback: [HomeworkFilter::class, 'values'], message: 'validation.invalidChoice')]
        public string $state = 'todo',
    ) {
    }
}
