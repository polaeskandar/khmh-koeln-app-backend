<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Interface;

interface AdministrationLockInterface
{
    /**
     * @param callable(): void $work
     */
    public function whileLocked(callable $work): void;
}
