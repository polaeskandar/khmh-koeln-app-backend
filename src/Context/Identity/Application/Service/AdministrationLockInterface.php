<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

interface AdministrationLockInterface
{
    /**
     * @param callable(): void $work
     */
    public function whileLocked(callable $work): void;
}
