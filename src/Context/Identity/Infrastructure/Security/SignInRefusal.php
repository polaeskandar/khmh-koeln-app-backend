<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Library\Types\Model\Type;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final readonly class SignInRefusal
{
    private const int SECONDS_PER_MINUTE = 60;

    public static function of(AuthenticationException $exception): HttpException
    {
        if ($exception instanceof InvalidApiToken) {
            return new UnauthorizedHttpException('Bearer', 'auth.unauthenticated');
        }

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new TooManyRequestsHttpException(self::retryAfter($exception), 'auth.tooManyAttempts');
        }

        return $exception instanceof CustomUserMessageAccountStatusException
            ? new AccessDeniedHttpException($exception->getMessageKey())
            : new UnauthorizedHttpException('Cookie', 'auth.invalidCredentials');
    }

    private static function retryAfter(TooManyLoginAttemptsAuthenticationException $exception): int
    {
        $minutes = Type::of($exception->getMessageData()['%minutes%'] ?? null)->toScalarOrNull();

        return (int) $minutes * self::SECONDS_PER_MINUTE;
    }
}
