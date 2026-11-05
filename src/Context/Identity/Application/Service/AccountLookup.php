<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\UserRepositoryInterface;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountLookup
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, User>
     */
    public function accounts(array $ids): ReadableCollection
    {
        return $this->users->findByIds($ids);
    }
}
