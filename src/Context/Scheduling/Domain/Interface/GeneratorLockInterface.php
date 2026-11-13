<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Interface;

interface GeneratorLockInterface
{
    /**
     * @param callable(): void $work
     */
    public function runUnlessHeld(callable $work): bool;
}
