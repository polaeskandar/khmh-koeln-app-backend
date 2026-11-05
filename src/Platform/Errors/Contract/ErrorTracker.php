<?php

declare(strict_types=1);

namespace App\Platform\Errors\Contract;

use Throwable;

interface ErrorTracker
{
    public function capture(Throwable $error): void;
}
