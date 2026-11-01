<?php

declare(strict_types=1);

namespace App\Interface\Application\Identity\UseCase\Query\ShowCurrentAccount;

final readonly class ShowCurrentAccountQuery
{
    public function __construct(
        public string $userId,
    ) {
    }
}
