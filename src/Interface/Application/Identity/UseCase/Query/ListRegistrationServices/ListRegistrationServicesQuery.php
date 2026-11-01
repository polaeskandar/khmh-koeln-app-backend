<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Query\ListRegistrationServices;

final readonly class ListRegistrationServicesQuery
{
    public function __construct(
        public int $limit = 200,
    ) {
    }
}
