<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Repository;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface UnconfirmedAccountRepositoryInterface
{
    /**
     * @return list<Uuid>
     */
    public function purgeExpired(DateTimeImmutable $registeredBefore, DateTimeImmutable $now): array;
}
