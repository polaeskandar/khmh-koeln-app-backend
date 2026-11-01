<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Lock;

use App\Context\Identity\Infrastructure\Lock\SymfonyAdministrationLock;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

final class OtherSession
{
    private ?Connection $connection = null;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function holdAdministrationLock(): void
    {
        $this->administrationLock()->acquire();
    }

    public function acquiresAdministrationLock(): bool
    {
        $lock = $this->administrationLock();

        if (!$lock->acquire()) {
            return false;
        }

        $lock->release();

        return true;
    }

    public function close(): void
    {
        $this->connection?->close();
    }

    private function administrationLock(): SharedLockInterface
    {
        $this->connection ??= DriverManager::getConnection(new Database($this->container)->connection()->getParams());

        return new LockFactory(new DoctrineDbalPostgreSqlStore($this->connection))->createLock(
            SymfonyAdministrationLock::RESOURCE,
            ttl: null,
            autoRelease: false,
        );
    }
}
