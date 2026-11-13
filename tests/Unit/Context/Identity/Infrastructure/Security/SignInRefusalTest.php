<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SignInRefusal;
use App\Library\Problem\Exception\Forbidden;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

final class SignInRefusalTest extends TestCase
{
    public function testTooManyAttemptsTellsWhenToRetryInSeconds(): void
    {
        $refusal = SignInRefusal::of(new TooManyLoginAttemptsAuthenticationException(threshold: 14));

        self::assertInstanceOf(TooManyRequestsHttpException::class, $refusal);
        self::assertSame('auth.tooManyAttempts', $refusal->getMessage());
        self::assertSame(['Retry-After' => 840], $refusal->getHeaders());
    }

    public function testAnAccountStatusKeepsItsCode(): void
    {
        $refusal = SignInRefusal::of(new CustomUserMessageAccountStatusException('auth.erasureScheduled'));

        self::assertInstanceOf(Forbidden::class, $refusal);
        self::assertSame('auth.erasureScheduled', $refusal->getMessage());
        self::assertSame([], $refusal->params);
    }

    public function testAnAccountStatusKeepsItsParams(): void
    {
        $refusal = SignInRefusal::of(new CustomUserMessageAccountStatusException('auth.erasureScheduled', [
            'erasesOn' => '2026-11-22',
        ]));

        self::assertInstanceOf(Forbidden::class, $refusal);
        self::assertSame('auth.erasureScheduled', $refusal->getMessage());
        self::assertSame(['erasesOn' => '2026-11-22'], $refusal->params);
    }

    public function testAnythingElseIsInvalidCredentials(): void
    {
        $refusal = SignInRefusal::of(new BadCredentialsException());

        self::assertInstanceOf(UnauthorizedHttpException::class, $refusal);
        self::assertSame('auth.invalidCredentials', $refusal->getMessage());
    }
}
