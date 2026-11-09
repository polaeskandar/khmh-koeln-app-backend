<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

#[AsEventListener]
final readonly class AccountStatusListener
{
    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $refusal = $user instanceof SecurityUser ? AccountRefusal::of($user) : null;

        if ($refusal !== null) {
            throw $refusal;
        }
    }
}
