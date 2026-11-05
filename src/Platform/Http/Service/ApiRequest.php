<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Library\Strings\Model\Text;
use Symfony\Component\HttpFoundation\Request;

final readonly class ApiRequest
{
    private const string BEARER = '#^Bearer +\S+$#i';

    private const string SCHEME = '#^Bearer +#i';

    public static function is(Request $request): bool
    {
        $path = Text::of($request->getPathInfo());

        return $path->toString() === '/api' || $path->startsWith('/api/');
    }

    public static function isBearer(Request $request): bool
    {
        $tokens = Text::of($request->getPathInfo())->startsWith('/api/auth/tokens');

        return $tokens || self::bearerToken($request) !== null;
    }

    public static function bearerToken(Request $request): ?string
    {
        $header = Text::of($request->headers->get('Authorization') ?? '');

        return $header->matches(self::BEARER)
            ? $header->replaceMatches(self::SCHEME, replacement: '')->toString()
            : null;
    }

    public static function isUnsafe(Request $request): bool
    {
        return !$request->isMethodSafe() && self::is($request);
    }
}
