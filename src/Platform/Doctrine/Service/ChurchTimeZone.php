<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsMiddleware]
final readonly class ChurchTimeZone implements Middleware
{
    public function __construct(
        #[Autowire(param: 'app.time_zone')]
        private string $timeZone,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new ChurchTimeZoneDriver($driver, $this->timeZone);
    }
}
