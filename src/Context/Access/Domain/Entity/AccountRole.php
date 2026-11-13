<?php

declare(strict_types=1);

namespace App\Context\Access\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'user_role', schema: 'access')]
final class AccountRole
{
    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: UuidType::NAME)]
    private Uuid $accountId;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'role_id', nullable: false, onDelete: 'CASCADE')]
    private Role $role;

    public function __construct(Uuid $accountId, Role $role)
    {
        $this->accountId = $accountId;
        $this->role = $role;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
