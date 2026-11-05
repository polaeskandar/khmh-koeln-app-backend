<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MoveMembershipRequest
{
    public function __construct(
        #[Assert\Uuid(message: 'meetings.chooseStage')]
        public ?string $stageId = null,
    ) {
    }
}
