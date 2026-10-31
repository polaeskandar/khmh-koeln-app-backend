<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use PHPUnit\Framework\TestCase;

final class SecurityUserTest extends TestCase
{
    public function testTheAccountIsKnownByItsIdWithTheUserRoleOnly(): void
    {
        $user = Accounts::approved('sara@example.org');
        $securityUser = SecurityUser::of($user);

        self::assertSame($user->id()->toRfc4122(), $securityUser->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $securityUser->getRoles());
        self::assertSame($user->passwordHash(), $securityUser->getPassword());
    }
}
