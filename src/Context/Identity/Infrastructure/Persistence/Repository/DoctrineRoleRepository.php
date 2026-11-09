<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Strings\Model\NameKey;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

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
    public function remove(Role $role): void
    {
        $this->entityManager->remove($role);
        $this->entityManager->flush();
    }

    #[Override]
    public function find(Uuid $id): ?Role
    {
        return $this->entityManager->find(Role::class, $id);
    }

    #[Override]
    public function findByName(string $name): ?Role
    {
        return $this->entityManager->getRepository(Role::class)->findOneBy(['name.key' => NameKey::of($name)]);
    }

    /**
     * @param list<Uuid> $ids
     */
    #[Override]
    public function findByIds(array $ids): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Role::class)->findBy(['id' => $ids]));
    }

    #[Override]
    public function byName(): ReadableCollection
    {
        return new ArrayCollection($this->entityManager->getRepository(Role::class)->findBy([], [
            'name.key' => 'ASC',
        ]));
    }
}
