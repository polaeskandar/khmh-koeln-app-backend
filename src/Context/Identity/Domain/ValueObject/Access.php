<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;

final readonly class Access
{
    public const string ATTRIBUTE = 'access';

    public function __construct(
        public Action $action,
        public Resource $resource,
    ) {
    }

    public function isAllowedFor(User $user): bool
    {
        return $user->status()->isActive() && $this->isHeldBy($user);
    }

    public function isHeldBy(User $user, ?Role $without = null): bool
    {
        return !$user
            ->roles()
            ->filter(fn (Role $role): bool => $role !== $without && $role->allows($this->action, $this->resource))
            ->isEmpty();
    }
}
