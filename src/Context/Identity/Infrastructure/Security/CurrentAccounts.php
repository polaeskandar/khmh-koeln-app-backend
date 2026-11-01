<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function of(#[\SensitiveParameter] TokenInterface $token): ?User
    {
        $account = $token->getUser();

        return $account instanceof SecurityUser
            ? $this->users->find(Uuid::fromString($account->getUserIdentifier()))
            : null;
    }
}
