<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Application\Service\SessionRenewalInterface;
use Override;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class SymfonySessionRenewal implements SessionRenewalInterface
{
    /**
     * @param UserProviderInterface<UserInterface> $accounts
     */
    public function __construct(
        private Security $security,
        #[Autowire(service: 'security.user.provider.concrete.accounts')]
        private UserProviderInterface $accounts,
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function renew(): void
    {
        if ($this->requests->getMainRequest()?->attributes->getBoolean('_stateless') !== false) {
            return;
        }

        $user = $this->security->getUser() ?? throw new AccessDeniedException();

        $this->security->login($this->accounts->refreshUser($user));
        $this->requests->getSession()->migrate(destroy: true);
    }
}
