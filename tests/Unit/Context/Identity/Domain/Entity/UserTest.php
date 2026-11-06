<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testAUserHoldsTheRolesItIsAssigned(): void
    {
        $user = Accounts::approved('sara@example.org');
        $servant = Roles::named('Servant');

        $user->assign($servant);
        self::assertSame([$servant], $user->roles()->getValues());

        $user->unassign($servant);
        self::assertTrue($user->roles()->isEmpty());
    }

    public function testAUserTakesTheStatusItIsGiven(): void
    {
        $user = Accounts::approved('sara@example.org');

        $user->changeStatus(AccountStatus::pending());

        self::assertTrue($user->status()->isPending());
    }
}
