<?php

declare(strict_types=1);

namespace App\Interface\Api\Organization\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class StageOrderRequest
{
    /**
     * @param list<string>|null $stageIds
     */
    public function __construct(
        #[Assert\NotNull(message: 'validation.invalidType')]
        #[Assert\All([new Assert\Uuid(message: 'validation.invalidType')])]
        public ?array $stageIds = null,
    ) {
    }
}
