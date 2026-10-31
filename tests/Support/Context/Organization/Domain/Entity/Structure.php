<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\ChurchService;
use App\Context\Organization\Domain\Entity\Stage;
use DateTimeImmutable;

final readonly class Structure
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-08T12:00:00+01:00');
    }

    public static function service(string $name = 'Sunday School'): ChurchService
    {
        return new ChurchService($name, description: null);
    }

    public static function stage(ChurchService $service, string $name = '3rd Class', int $position = 0): Stage
    {
        return new Stage($service, $name, description: null, position: $position);
    }
}
