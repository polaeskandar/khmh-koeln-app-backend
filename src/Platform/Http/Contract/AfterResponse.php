<?php

declare(strict_types=1);

namespace App\Platform\Http\Contract;

interface AfterResponse
{
    /**
     * @param callable(): void $work
     */
    public function run(string $label, callable $work): void;
}
