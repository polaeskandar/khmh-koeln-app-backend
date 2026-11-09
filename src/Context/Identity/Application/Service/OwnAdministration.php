<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class OwnAdministration
{
    public static function ensureKept(Account $account, Role $role, Uuid $actorId): void
    {
        if (!$account->id()->equals($actorId) || !RolePermissions::roleAllows($role, Action::Update, Resource::Roles)) {
            return;
        }

        if (!RolePermissions::accountHasWithout($account, $role, Action::Update, Resource::Roles)) {
            throw new RuleViolation('roles.ownAdminRole');
        }
    }
}
