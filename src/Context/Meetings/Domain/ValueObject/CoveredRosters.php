<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

final readonly class CoveredRosters
{
    /**
     * @param list<string>|null $serviceIds the services covered with all their stages; null when every roster is
     * @param list<string>|null $stageIds the stages covered one by one; null when every roster is
     */
    public function __construct(
        public ?array $serviceIds,
        public ?array $stageIds,
    ) {
    }
}
