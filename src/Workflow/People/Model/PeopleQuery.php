<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class PeopleQuery
{
    public function __construct(
        public string $status,
        public ?string $serviceId,
        public ?string $stageId,
        public string $text,
        public int $page,
    ) {
    }
}
