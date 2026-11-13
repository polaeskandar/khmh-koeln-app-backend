<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use Symfony\Component\Uid\Uuid;

final readonly class Accounts
{
    public static function active(string $email, string $passwordHash = 'not-a-real-hash'): User
    {
        return new User(
            new Profile(Email::of($email), DisplayName::of('Sara Hanna'), Language::German),
            passwordHash: $passwordHash,
            status: AccountStatus::active(),
            registration: Registration::none(),
            roles: [],
        );
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @return array<string, string|null>
     */
    public static function row(array $columns = []): array
    {
        return [
            'id' => Uuid::v7()->toRfc4122(),
            'email' => 'sara@example.org',
            'display_name' => 'Sara Hanna',
            'password_hash' => 'not-a-real-hash',
            'language' => 'de',
            'created_at' => '2026-11-08 12:00:00+01',
            ...$columns,
        ];
    }
}
