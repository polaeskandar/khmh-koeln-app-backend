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
        $account = Accounts::approved('sara@example.org');
        $account->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));
        $account->assign(Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal)));

        self::assertTrue(RolePermissions::accountHas($account, Action::Create, Resource::Attendance));
        self::assertTrue(RolePermissions::accountHas($account, Action::Read, Resource::ParentPortal));
        self::assertFalse(RolePermissions::accountHas($account, Action::Delete, Resource::Users));
    }

    public function testOnlyAnActiveAccountHasWhatItsRolesAllow(): void
    {
        $account = Accounts::approved('sara@example.org');
        $account->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertTrue(RolePermissions::activeAccountHas($account, Action::Create, Resource::Attendance));

        $account->changeStatus(AccountStatus::pending());
        self::assertFalse(RolePermissions::activeAccountHas($account, Action::Create, Resource::Attendance));
    }

    public function testAnAccountHasWithoutARoleOnlyWhatItsOtherRolesAllow(): void
    {
        $account = Accounts::approved('sara@example.org');
        $administrator = Roles::named('Administrator', new Permission(Action::Update, Resource::Roles));
        $account->assign($administrator);
        $account->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertFalse(RolePermissions::accountHasWithout(
            $account,
            $administrator,
            Action::Update,
            Resource::Roles,
        ));

        self::assertTrue(RolePermissions::accountHasWithout(
            $account,
            $administrator,
            Action::Create,
            Resource::Attendance,
        ));
    }
}
