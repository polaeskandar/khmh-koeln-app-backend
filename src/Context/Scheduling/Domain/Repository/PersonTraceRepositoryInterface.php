<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface PersonTraceRepositoryInterface
{
    /**
     * @return list<Uuid>
     */
    public function presentAt(Uuid $personId): array;

    public function forget(Uuid $personId): int;
}
