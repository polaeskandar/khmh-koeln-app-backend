<?php

declare(strict_types=1);

namespace App\Platform\Errors\Contract;

use Throwable;

interface ErrorTrackerInterface
{
    public function capture(Throwable $error): void;
}
