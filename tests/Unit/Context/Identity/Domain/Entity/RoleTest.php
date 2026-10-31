<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\RoleName;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use App\Tests\Support\Library\Problem\Exception\ThrownFieldErrors;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function testARoleAllowsWhatItsPermissionsAllow(): void
    {
        $readServices = new Permission(Action::Read, Resource::Services);
        $role = Roles::named('  Choir   leader ', $readServices, $readServices);

        self::assertSame('Choir leader', $role->name()->value);
        self::assertSame([$readServices], $role->permissions()->getValues());
        self::assertTrue($role->allows(Action::Read, Resource::Services));
        self::assertFalse($role->allows(Action::Update, Resource::Services));
    }

    public function testARevokedPermissionNoLongerAllows(): void
    {
        $readServices = new Permission(Action::Read, Resource::Services);
        $role = Roles::named('Choir leader', $readServices);

        $role->revoke($readServices);

        self::assertFalse($role->allows(Action::Read, Resource::Services));
    }

    public function testARoleNeedsAName(): void
    {
        self::assertSame(
            ['name' => ['code' => 'roles.nameRequired']],
            ThrownFieldErrors::of(static fn (): RoleName => RoleName::of(' ')),
        );

        self::assertSame(
            ['name' => ['code' => 'validation.tooLong', 'params' => ['max' => 80]]],
            ThrownFieldErrors::of(static fn (): RoleName => RoleName::of(str_repeat('a', times: 81))),
        );
    }
}
