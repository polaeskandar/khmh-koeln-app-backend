<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Service;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Override;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @phpstan-import-type Params from DriverManager
 */
#[Exclude]
final class ChurchTimeZoneDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly string $timeZone,
    ) {
        parent::__construct($driver);
    }

    /**
     * @param Params $params
     */
    #[Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $connection = parent::connect($params);
        $connection->exec('SET TIME ZONE ' . $connection->quote($this->timeZone));

        return $connection;
    }
}
