<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class OwnAdministration
{
    public static function ensureKept(User $user, Role $role, Uuid $actorId): void
    {
        if (!$user->id()->equals($actorId) || !$role->allows(Action::Update, Resource::Roles)) {
            return;
        }

        if (!$user->can(Action::Update, Resource::Roles, without: $role)) {
            throw new RuleViolation('roles.ownAdminRole');
        }
    }
}
