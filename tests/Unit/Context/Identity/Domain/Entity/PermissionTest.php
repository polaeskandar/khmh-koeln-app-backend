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

        self::assertSame('{"action":"update","resource":"sessions"}', json_encode($permission, JSON_THROW_ON_ERROR));
        self::assertTrue($permission->isFor(Action::Update, Resource::Sessions));
        self::assertFalse($permission->isFor(Action::Read, Resource::Sessions));
    }
}
