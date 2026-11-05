<?php

declare(strict_types=1);

namespace App\Context\Privacy\Domain\Repository;

use Symfony\Component\Uid\Uuid;

interface PersonTraceRepositoryInterface
{
    public function forget(Uuid $personId, ?string $group): int;
}
