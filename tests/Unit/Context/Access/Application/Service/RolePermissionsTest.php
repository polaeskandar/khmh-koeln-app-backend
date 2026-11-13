<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Access\Application\Service;

use App\Context\Access\Application\Service\RolePermissions;
use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Tests\Support\Context\Access\Domain\Entity\Roles;
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
        $roles = [
            Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)),
            Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal)),
        ];

        self::assertTrue(RolePermissions::isGrantedToAny($roles, Action::Create, Resource::Attendance));
        self::assertTrue(RolePermissions::isGrantedToAny($roles, Action::Read, Resource::ParentPortal));
        self::assertFalse(RolePermissions::isGrantedToAny($roles, Action::Delete, Resource::Users));
        self::assertFalse(RolePermissions::isGrantedToAny([], Action::Read, Resource::ParentPortal));
    }

    public function testAnAccountHasWithoutARoleOnlyWhatItsOtherRolesAllow(): void
    {
        $administrator = Roles::named('Administrator', new Permission(Action::Update, Resource::Roles));
        $roles = [$administrator, Roles::named('Servant', new Permission(Action::Create, Resource::Attendance))];

        self::assertFalse(RolePermissions::isGrantedWithout($roles, $administrator, Action::Update, Resource::Roles));

        self::assertTrue(RolePermissions::isGrantedWithout(
            $roles,
            $administrator,
            Action::Create,
            Resource::Attendance,
        ));
    }
}
