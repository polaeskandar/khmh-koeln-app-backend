<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

#[AsEventListener]
final readonly class AccountStatusListener
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $status = $user instanceof SecurityUser ? $user->status() : null;

        if ($status?->disabledAt !== null) {
            throw new CustomUserMessageAccountStatusException('auth.disabled');
        }

        if ($status?->isPending() === true) {
            throw new CustomUserMessageAccountStatusException('auth.pending');
        }
    }
}
