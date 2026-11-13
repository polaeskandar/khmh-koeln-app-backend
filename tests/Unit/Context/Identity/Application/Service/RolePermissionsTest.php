<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\RolePermissions;
use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RolePermissionsTest extends TestCase
{
    public function testARoleAllowsWhatItsPermissionsAllow(): void
    {
        $role = Roles::named('Choir leader', new Permission(Action::Read, Resource::Services));

        self::assertTrue(RolePermissions::isGrantedToRole($role, Action::Read, Resource::Services));
        self::assertFalse(RolePermissions::isGrantedToRole($role, Action::Update, Resource::Services));
    }

    public function testAnAccountHasWhatAnyOfItsRolesAllows(): void
    {
        $user = Accounts::active('sara@example.org');
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));
        $user->assign(Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal)));

        self::assertTrue(RolePermissions::isGrantedToAccount($user, Action::Create, Resource::Attendance));
        self::assertTrue(RolePermissions::isGrantedToAccount($user, Action::Read, Resource::ParentPortal));
        self::assertFalse(RolePermissions::isGrantedToAccount($user, Action::Delete, Resource::Users));
    }

    public function testOnlyAnActiveAccountHasWhatItsRolesAllow(): void
    {
        $user = Accounts::active('sara@example.org');
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertTrue(RolePermissions::isGrantedToActiveAccount($user, Action::Create, Resource::Attendance));

        $user->changeStatus($user->status()->disabledAt(new DateTimeImmutable('2026-11-08T12:00:00+01:00')));
        self::assertFalse(RolePermissions::isGrantedToActiveAccount($user, Action::Create, Resource::Attendance));
    }

    public function testAnAccountHasWithoutARoleOnlyWhatItsOtherRolesAllow(): void
    {
        $user = Accounts::active('sara@example.org');
        $administrator = Roles::named('Administrator', new Permission(Action::Update, Resource::Roles));
        $user->assign($administrator);
        $user->assign(Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)));

        self::assertFalse(RolePermissions::isGrantedToAccountWithout(
            $user,
            $administrator,
            Action::Update,
            Resource::Roles,
        ));

        self::assertTrue(RolePermissions::isGrantedToAccountWithout(
            $user,
            $administrator,
            Action::Create,
            Resource::Attendance,
        ));
    }
}
