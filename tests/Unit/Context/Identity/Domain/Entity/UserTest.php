<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testAUserCanWhatAnyOfItsRolesAllows(): void
    {
        $user = User::approved(
            new Profile(Email::of('sara@example.org'), DisplayName::of('Sara'), Language::German),
            passwordHash: 'not-a-real-hash',
            approvedAt: new DateTimeImmutable('2026-11-08T12:00:00+01:00'),
            roles: [
                Roles::named('Servant', new Permission(Action::Create, Resource::Attendance)),
                Roles::named('Parent', new Permission(Action::Read, Resource::ParentPortal)),
            ],
        );

        self::assertTrue($user->can(Action::Create, Resource::Attendance));
        self::assertTrue($user->can(Action::Read, Resource::ParentPortal));
        self::assertFalse($user->can(Action::Delete, Resource::Users));
    }
}
