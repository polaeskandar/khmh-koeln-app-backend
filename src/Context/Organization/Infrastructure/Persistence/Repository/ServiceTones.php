<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Entity\Service;

final readonly class ServiceTones
{
    public static function of(Service $service): int
    {
        return $service->tone()->value;
    }

    public static function pendingOf(object $entity): ?int
    {
        return $entity instanceof Service ? self::of($entity) : null;
    }
}
