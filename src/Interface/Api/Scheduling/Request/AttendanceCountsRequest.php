<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttendanceCountsRequest
{
    /**
     * @param list<string> $sessionIds
     */
    public function __construct(
        #[Assert\All([new Assert\Uuid(message: 'validation.invalidType')])]
        public array $sessionIds = [],
    ) {
    }
}
