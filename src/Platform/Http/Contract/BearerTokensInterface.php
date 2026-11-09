<?php

declare(strict_types=1);

namespace App\Platform\Http\Contract;

use Symfony\Component\HttpFoundation\Request;

interface BearerTokensInterface
{
    public function tokenOf(Request $request): ?string;
}
