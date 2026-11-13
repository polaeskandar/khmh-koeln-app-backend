<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Access\Domain\Entity;

use App\Context\Access\Domain\Entity\Permission;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function testAPermissionIsAnActionOnAResource(): void
    {
        $permission = new Permission(Action::Update, Resource::Sessions);

        self::assertSame(Action::Update, $permission->action());
        self::assertSame(Resource::Sessions, $permission->resource());
    }
}
