<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final readonly class AccountRefusal
{
    public static function of(SecurityUser $user): ?AuthenticationException
    {
        $account = $user->account();
        $erasesOn = $account?->erasesOn();

        if ($account?->status()->disabledAt !== null) {
            return new BadCredentialsException();
        }

        if ($erasesOn === null) {
            return null;
        }

        return new CustomUserMessageAccountStatusException('auth.erasureScheduled', [
            'erasesOn' => $erasesOn->toString(),
        ]);
    }
}
