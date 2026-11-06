<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StageRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'stages.nameRequired')]
        public ?string $name = null,
        public ?string $description = null,
    ) {
    }
}
