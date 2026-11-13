<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testAUserHoldsTheRolesItIsAssigned(): void
    {
        $user = Accounts::active('sara@example.org');
        $servant = Roles::named('Servant');

        $user->assign($servant);
        self::assertSame([$servant], $user->roles()->getValues());

        $user->unassign($servant);
        self::assertTrue($user->roles()->isEmpty());
    }

    public function testAUserTakesTheStatusItIsGiven(): void
    {
        $user = Accounts::active('sara@example.org');

        $user->changeStatus($user->status()->disabledAt(new DateTimeImmutable('2026-11-08T12:00:00+01:00')));

        self::assertFalse($user->status()->isActive());
    }
}
