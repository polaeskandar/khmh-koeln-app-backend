<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\RolePermissions;
use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use PHPUnit\Framework\TestCase;

final class RolePermissionsTest extends TestCase
{
    public function testARoleAllowsWhatItsPermissionsAllow(): void
    {
        $role = Roles::named('Choir leader', new Permission(Action::Read, Resource::Services));

        self::assertTrue(RolePermissions::roleAllows($role, Action::Read, Resource::Services));
        self::assertFalse(RolePermissions::roleAllows($role, Action::Update, Resource::Services));
    }

    public function testAnAccountHasWhatAnyOfItsRolesAllows(): void
    {
        $user = Accounts::approved('sara@example.org');
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));
        $user->assign(Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal)));

        self::assertTrue(RolePermissions::accountHas($user, Action::Create, Resource::Attendance));
        self::assertTrue(RolePermissions::accountHas($user, Action::Read, Resource::ParentPortal));
        self::assertFalse(RolePermissions::accountHas($user, Action::Delete, Resource::Users));
    }

    public function testOnlyAnActiveAccountHasWhatItsRolesAllow(): void
    {
        $user = Accounts::approved('sara@example.org');
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertTrue(RolePermissions::activeAccountHas($user, Action::Create, Resource::Attendance));

        $user->changeStatus(AccountStatus::pending());
        self::assertFalse(RolePermissions::activeAccountHas($user, Action::Create, Resource::Attendance));
    }

    public function testAnAccountHasWithoutARoleOnlyWhatItsOtherRolesAllow(): void
    {
        $user = Accounts::approved('sara@example.org');
        $administrator = Roles::named('Administrator', new Permission(Action::Update, Resource::Roles));
        $user->assign($administrator);
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertFalse(RolePermissions::accountHasWithout($user, $administrator, Action::Update, Resource::Roles));

        self::assertTrue(RolePermissions::accountHasWithout(
            $user,
            $administrator,
            Action::Create,
            Resource::Attendance,
        ));
    }
}
