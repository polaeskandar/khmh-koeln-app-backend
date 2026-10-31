<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final readonly class HttpStatusFallback
{
    private const array CODES = [
        Response::HTTP_UNAUTHORIZED => 'auth.unauthenticated',
        Response::HTTP_TOO_MANY_REQUESTS => 'tooManyRequests',
        Response::HTTP_SERVICE_UNAVAILABLE => 'unavailable',
    ];

    /**
     * @param ReadableCollection<int, Throwable> $chain
     */
    public static function of(ReadableCollection $chain): ?Problem
    {
        $exception = $chain
            ->filter(static fn (Throwable $cause): bool => $cause instanceof HttpExceptionInterface)
            ->first();

        if (!$exception instanceof HttpExceptionInterface) {
            return null;
        }

        $status = $exception->getStatusCode();
        $fallback = $status >= Response::HTTP_INTERNAL_SERVER_ERROR ? 'internal' : 'invalidRequest';

        return new Problem($status, self::CODES[$status] ?? $fallback);
    }
}
