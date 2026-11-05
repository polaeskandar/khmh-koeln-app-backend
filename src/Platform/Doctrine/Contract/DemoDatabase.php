<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Contract;

interface DemoDatabase
{
    public function isLocal(): bool;

    /**
     * @param callable(): void $load
     */
    public function replace(callable $load): void;
}
