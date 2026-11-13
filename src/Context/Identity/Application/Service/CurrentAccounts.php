<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentAccounts
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function get(Uuid $userId): User
    {
        return $this->users->find($userId) ?? throw NotFound::of('account');
    }

    public function changeLanguage(Uuid $userId, Language $language): void
    {
        $this->users->withWriteLock($userId, static fn (User $user) => $user->changeLanguage($language));
    }
}
