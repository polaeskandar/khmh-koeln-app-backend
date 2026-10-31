<?php

declare(strict_types=1);

namespace App\Platform\Problem\Contract;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

interface ProblemResponder
{
    public function respond(Throwable $exception): Response;
}
