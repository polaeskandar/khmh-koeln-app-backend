<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Persistence\Repository;

use App\Context\Access\Domain\Entity\AccountRole;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Repository\AccountRoleRepositoryInterface;
use App\Library\Types\Model\Integers;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAccountRoleRepository implements AccountRoleRepositoryInterface
{
    private const string FORGET = 'DELETE FROM access.user_role WHERE user_id = :account';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function give(Uuid $accountId, Role $role): void
    {
        $this->entityManager->persist(new AccountRole($accountId, $role));
        $this->entityManager->flush();
    }

    #[Override]
    public function take(Uuid $accountId, Role $role): void
    {
        $held = $this->entityManager->find(AccountRole::class, ['accountId' => $accountId, 'role' => $role]);

        if ($held === null) {
            return;
        }

        $this->entityManager->remove($held);
        $this->entityManager->flush();
    }

    #[Override]
    public function forget(Uuid $accountId): int
    {
        return Integers::of($this->entityManager->getConnection()->executeStatement(self::FORGET, [
            'account' => $accountId->toRfc4122(),
        ]));
    }
}
