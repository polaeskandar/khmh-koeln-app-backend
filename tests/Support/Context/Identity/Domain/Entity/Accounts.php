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
use App\Context\Identity\Domain\ValueObject\Username;
use Symfony\Component\Uid\Uuid;

final readonly class Accounts
{
    public static function active(string $email, string $passwordHash = 'not-a-real-hash'): User
    {
        return new User(
            Username::of(self::usernameOf($email)),
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
        $email = $columns['email'] ?? 'sara@example.org';

        return [
            'id' => Uuid::v7()->toRfc4122(),
            'username' => self::usernameOf($email),
            'email' => $email,
            'display_name' => 'Sara Hanna',
            'password_hash' => 'not-a-real-hash',
            'language' => 'de',
            'created_at' => '2026-11-08 12:00:00+01',
            ...$columns,
        ];
    }

    public static function usernameOf(string $email): string
    {
        $local = strtolower(explode('@', $email)[0]);
        $allowed = (string) preg_replace(['/[^a-z0-9._-]/', '/([._-])[._-]+/', '/^[^a-z]+/'], ['', '$1', ''], $local);
        $username = rtrim(substr($allowed, offset: 0, length: 32), characters: '._-');

        return strlen($username) < 3 ? 'user' . $username : $username;
    }
}
