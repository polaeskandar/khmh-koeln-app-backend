<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\RoleName;
use App\Library\Persistence\Mapping\TimestampedEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'role', schema: 'identity')]
#[ORM\UniqueConstraint(name: 'role_name_key_unique', columns: ['name_key'])]
final class Role extends TimestampedEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private readonly Uuid $id;

    #[ORM\Embedded(RoleName::class, columnPrefix: false)]
    private RoleName $name;

    /**
     * @var Collection<int, Permission>
     */
    #[ORM\ManyToMany(targetEntity: Permission::class)]
    #[ORM\JoinTable(name: 'role_permission', schema: 'identity')]
    #[ORM\JoinColumn(name: 'role_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'permission_id', onDelete: 'CASCADE')]
    private Collection $permissions;

    public function __construct(RoleName $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->permissions = new ArrayCollection();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function name(): RoleName
    {
        return $this->name;
    }

    /**
     * @return ReadableCollection<int, Permission>
     */
    public function permissions(): ReadableCollection
    {
        return $this->permissions;
    }

    public function allows(Action $action, Resource $resource): bool
    {
        return !$this->permissions
            ->filter(static fn (Permission $permission): bool => $permission->isFor($action, $resource))
            ->isEmpty();
    }

    public function grant(Permission $permission): void
    {
        if ($this->permissions->contains($permission)) {
            return;
        }

        $this->permissions->add($permission);
    }

    public function revoke(Permission $permission): void
    {
        $this->permissions->removeElement($permission);
    }
}
