<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Application\Service;

use App\Context\Identity\Application\Service\PermissionCatalog;
use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use PHPUnit\Framework\TestCase;

final class PermissionCatalogTest extends TestCase
{
    public function testARoleGrantsItsPermissionsInTheOrderOfTheCatalog(): void
    {
        $role = Roles::named(
            'Choir leader',
            new Permission(Action::Read, Resource::Lessons),
            new Permission(Action::Update, Resource::Services),
            new Permission(Action::Read, Resource::Services),
        );

        self::assertEquals(
            [
                new Access(Action::Read, Resource::Services),
                new Access(Action::Update, Resource::Services),
                new Access(Action::Read, Resource::Lessons),
            ],
            PermissionCatalog::grantsOf($role),
        );

        self::assertSame([], PermissionCatalog::grantsOf(Roles::named('Visitor')));
    }
}
