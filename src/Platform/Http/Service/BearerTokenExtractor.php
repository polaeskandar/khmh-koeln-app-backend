<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Platform\Http\Contract\BearerTokensInterface;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

final readonly class BearerTokenExtractor implements BearerTokensInterface, AccessTokenExtractorInterface
{
    #[Override]
    public function tokenOf(Request $request): ?string
    {
        return ApiRequest::bearerToken($request);
    }

    #[Override]
    public function extractAccessToken(Request $request): ?string
    {
        return $this->tokenOf($request);
    }
}
