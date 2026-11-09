<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final readonly class AccountRefusal
{
    public static function of(SecurityUser $user): ?CustomUserMessageAccountStatusException
    {
        $account = $user->account();
        $status = $account?->status();
        $erasesOn = $account?->erasesOn();

        if ($status?->disabledAt !== null) {
            return new CustomUserMessageAccountStatusException('auth.disabled');
        }

        if ($erasesOn !== null) {
            return new CustomUserMessageAccountStatusException('auth.erasureScheduled', [
                'erasesOn' => $erasesOn->toString(),
            ]);
        }

        if ($status?->isAwaitingEmailConfirmation() === true) {
            return new CustomUserMessageAccountStatusException('auth.unconfirmed');
        }

        return $status?->isPending() === true ? new CustomUserMessageAccountStatusException('auth.pending') : null;
    }
}
