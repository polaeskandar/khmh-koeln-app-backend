<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\Forbidden;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final readonly class SignInRefusal
{
    private const int SECONDS_PER_MINUTE = 60;

    public static function of(AuthenticationException $exception): HttpException|Forbidden
    {
        if ($exception instanceof InvalidApiToken) {
            return new UnauthorizedHttpException('Bearer', 'auth.unauthenticated');
        }

        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new TooManyRequestsHttpException(self::retryAfter($exception), 'auth.tooManyAttempts');
        }

        return $exception instanceof CustomUserMessageAccountStatusException
            ? new Forbidden($exception->getMessageKey(), self::paramsOf($exception))
            : new UnauthorizedHttpException('Cookie', 'auth.invalidCredentials');
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    private static function paramsOf(CustomUserMessageAccountStatusException $exception): array
    {
        return Collections::rekeyed(
            new ArrayCollection($exception->getMessageData()),
            keyOf: static fn (int|string $key): string => (string) $key,
        )->map(static fn (mixed $value): string|int|float|bool|null => Type::of($value)->toScalarOrNull())->toArray();
    }

    private static function retryAfter(TooManyLoginAttemptsAuthenticationException $exception): int
    {
        $minutes = Type::of($exception->getMessageData()['%minutes%'] ?? null)->toScalarOrNull();

        return (int) $minutes * self::SECONDS_PER_MINUTE;
    }
}
