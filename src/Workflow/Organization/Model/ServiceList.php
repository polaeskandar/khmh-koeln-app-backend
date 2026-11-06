<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

final readonly class ServiceList
{
    /**
     * @param list<ServiceOverview> $items
     */
    public function __construct(
        public array $items,
    ) {
    }
}
