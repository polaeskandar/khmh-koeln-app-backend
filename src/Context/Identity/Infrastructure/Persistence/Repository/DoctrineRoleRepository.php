<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Strings\Model\NameKey;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRoleRepository implements RoleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function save(Role $role): void
    {
        $this->entityManager->persist($role);
        $this->entityManager->flush();
    }

    #[Override]
    public function findByName(string $name): ?Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['name.key' => NameKey::of($name)]);
    }
}
