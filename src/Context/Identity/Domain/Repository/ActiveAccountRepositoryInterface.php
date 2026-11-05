<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

interface ActiveAccountRepositoryInterface
{
    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, name: string, email: string}>
     */
    public function listed(int $limit): array;
}
