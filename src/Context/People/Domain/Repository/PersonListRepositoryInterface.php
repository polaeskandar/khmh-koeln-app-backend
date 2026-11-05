<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\ValueObject\PersonFilter;
use Symfony\Component\Uid\Uuid;

interface PersonListRepositoryInterface
{
    /**
     * @return list<Uuid>
     */
    public function page(PersonFilter $filter, int $offset, int $limit): array;

    public function count(PersonFilter $filter): int;

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    public function byName(array $ids): array;
}
