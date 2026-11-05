<?php

declare(strict_types=1);

namespace App\Platform\Doctrine\Contract;

interface Transactions
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;

    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function rehearse(callable $work): mixed;
}
