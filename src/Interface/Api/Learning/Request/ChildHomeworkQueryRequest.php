<?php

declare(strict_types=1);

namespace App\Interface\Api\Learning\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChildHomeworkQueryRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['todo', 'done'], message: 'validation.invalidChoice')]
        public string $state = 'todo',
    ) {
    }
}
