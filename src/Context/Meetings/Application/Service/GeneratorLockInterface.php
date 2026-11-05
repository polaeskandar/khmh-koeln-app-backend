<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

interface GeneratorLockInterface
{
    /**
     * @param callable(): void $work
     */
    public function runUnlessHeld(callable $work): bool;
}
