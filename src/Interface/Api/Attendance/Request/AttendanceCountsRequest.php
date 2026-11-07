<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AttendanceCountsRequest
{
    /**
     * @param list<string> $sessionIds
     */
    public function __construct(
        #[Assert\Count(max: 100, maxMessage: 'validation.tooMany')]
        #[Assert\All([new Assert\Uuid(message: 'validation.invalidType')])]
        public array $sessionIds = [],
    ) {
    }
}
