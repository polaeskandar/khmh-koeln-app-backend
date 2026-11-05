<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Contract;

interface Transactions
{
    /**
     * @param callable(): void $work
     */
    public function run(callable $work): void;

    public function recover(): void;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function rehearse(callable $work): mixed;
}
