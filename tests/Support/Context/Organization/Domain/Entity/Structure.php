<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Organization\Domain\Entity;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\ValueObject\Description;
use App\Context\Organization\Domain\ValueObject\ServiceName;
use App\Context\Organization\Domain\ValueObject\StageName;
use App\Context\Organization\Domain\ValueObject\Tone;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class Structure
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-08T12:00:00+01:00');
    }

    public static function service(string $name = 'Sunday School', ?string $description = null): Service
    {
        return new Service(
            ServiceName::of($name),
            Description::of($description, max: 2000),
            Tone::leastUsed(new ArrayCollection()),
        );
    }

    public static function stage(Service $service, string $name = '3rd Class', int $position = 0): Stage
    {
        return new Stage($service, StageName::of($name), Description::of(null, max: 500), $position);
    }
}
