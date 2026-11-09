<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\DisplayName;
use App\Context\Identity\Domain\ValueObject\Email;
use App\Context\Identity\Domain\ValueObject\Profile;
use App\Context\Identity\Domain\ValueObject\Registration;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class Accounts
{
    public static function approved(string $email, string $passwordHash = 'not-a-real-hash'): Account
    {
        return new Account(
            new Profile(Email::of($email), DisplayName::of('Sara Hanna'), Language::German),
            passwordHash: $passwordHash,
            status: AccountStatus::pending()->approvedOn(new DateTimeImmutable('2026-11-08T12:00:00+01:00')),
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
            'approved_at' => '2026-11-08 12:00:00+01',
            'email_confirmed_at' => '2026-11-08 12:00:00+01',
            'created_at' => '2026-11-08 12:00:00+01',
            ...$columns,
        ];
    }
}
