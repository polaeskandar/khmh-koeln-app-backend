<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\ValueObject;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\Access;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase
{
    public function testAnAccessIsHeldThroughAnyRoleUnlessThatRoleIsLeftOut(): void
    {
        $servant = Roles::named('Servant', new Permission(Action::Create, Resource::Attendance));

        $user = User::approved(
            new Profile(Email::of('sara@example.org'), DisplayName::of('Sara'), Language::German),
            passwordHash: 'not-a-real-hash',
            approvedAt: new DateTimeImmutable('2026-11-08T12:00:00+01:00'),
            roles: [$servant, Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal))],
        );

        self::assertTrue(new Access(Action::Create, Resource::Attendance)->isHeldBy($user));
        self::assertTrue(new Access(Action::Read, Resource::ParentPortal)->isHeldBy($user));
        self::assertFalse(new Access(Action::Delete, Resource::Users)->isHeldBy($user));
        self::assertFalse(new Access(Action::Create, Resource::Attendance)->isHeldBy($user, without: $servant));
    }
}
