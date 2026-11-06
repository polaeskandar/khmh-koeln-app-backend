<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
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
