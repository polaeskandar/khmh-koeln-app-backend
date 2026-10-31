<?php

declare(strict_types=1);

namespace App\Platform\Http\Service;

use App\Library\Strings\Model\Text;
use Symfony\Component\HttpFoundation\Request;

final readonly class ApiRequest
{
    public static function is(Request $request): bool
    {
        $path = Text::of($request->getPathInfo());

        return $path->toString() === '/api' || $path->startsWith('/api/');
    }
}
