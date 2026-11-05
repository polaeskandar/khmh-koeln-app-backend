<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Platform\Http\Contract\BearerTokens;
use Override;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

final readonly class BearerTokenExtractor implements BearerTokens, AccessTokenExtractorInterface
{
    #[Override]
    public function of(Request $request): ?string
    {
        return ApiRequest::bearerToken($request);
    }

    #[Override]
    public function extractAccessToken(Request $request): ?string
    {
        return $this->of($request);
    }
}
