<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testAnAccountHoldsTheRolesItIsAssigned(): void
    {
        $account = Accounts::approved('sara@example.org');
        $servant = Roles::named('Servant');

        $account->assign($servant);
        self::assertSame([$servant], $account->roles()->getValues());

        $account->unassign($servant);
        self::assertTrue($account->roles()->isEmpty());
    }

    public function testAnAccountTakesTheStatusItIsGiven(): void
    {
        $account = Accounts::approved('sara@example.org');

        $account->changeStatus(AccountStatus::pending());

        self::assertTrue($account->status()->isPending());
    }
}
