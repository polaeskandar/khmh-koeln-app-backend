<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

final readonly class BearerRequests implements RequestMatcherInterface
{
    #[Override]
    public function matches(Request $request): bool
    {
        return ApiRequest::is($request) && ApiRequest::isBearer($request);
    }
}
