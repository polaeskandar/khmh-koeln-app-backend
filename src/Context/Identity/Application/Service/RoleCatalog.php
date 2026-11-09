<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Context\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RoleCatalog
{
    public function __construct(
        private RoleRepositoryInterface $roles,
        private RoleHolderRepositoryInterface $holders,
    ) {
    }

    /**
     * @return ReadableCollection<int, Role>
     */
    public function byName(): ReadableCollection
    {
        return $this->roles->byName();
    }

    /**
     * @return array<string, int>
     */
    public function holderCounts(): array
    {
        return $this->holders->holderCounts();
    }

    public function find(string $id): Role
    {
        $role = Uuid::isValid($id) ? $this->roles->find(Uuid::fromString($id)) : null;

        return $role ?? throw NotFound::of('role');
    }

    /**
     * @return ReadableCollection<int, User>
     */
    public function holdersOf(Role $role): ReadableCollection
    {
        return $this->holders->holdersOf($role);
    }
}
