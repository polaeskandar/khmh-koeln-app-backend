<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Library\Types\Model\ListOf;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRoleHolderRepository implements RoleHolderRepositoryInterface
{
    private const string HOLDERS = <<<'DQL'
        SELECT u FROM App\Context\Identity\Domain\Entity\User u
        JOIN u.roles r
        WHERE r.name.key = :key
          AND u.status.approvedAt IS NOT NULL
          AND u.status.disabledAt IS NULL
        DQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function activeHolders(string $roleNameKey): ReadableCollection
    {
        $query = $this->entityManager->createQuery(self::HOLDERS)->setParameter('key', $roleNameKey);

        return new ArrayCollection(ListOf::instances($query->getResult(), User::class));
    }
}
