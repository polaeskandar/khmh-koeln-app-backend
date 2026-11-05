<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Lock;

use App\Context\Meetings\Application\Service\GeneratorLockInterface;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

final readonly class SymfonyGeneratorLock implements GeneratorLockInterface
{
    public const string RESOURCE = 'session-generator';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function runUnlessHeld(callable $work): bool
    {
        $lock = new LockFactory(new DoctrineDbalPostgreSqlStore($this->connection))->createLock(
            self::RESOURCE,
            ttl: null,
        );

        if (!$lock->acquire()) {
            return false;
        }

        try {
            $work();
        } finally {
            $lock->release();
        }

        return true;
    }
}
