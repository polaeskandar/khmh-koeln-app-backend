<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Language;
use App\Context\Identity\Domain\ValueObject\AccountStatus;
use App\Context\Identity\Domain\ValueObject\Registration;
use App\Context\Identity\Domain\ValueObject\Username;
use App\Context\People\Domain\ValueObject\PersonName;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class Accounts
{
    private const array HANDLES = ['email' => true, 'display_name' => true];

    public static function active(string $email, string $passwordHash = 'not-a-real-hash'): User
    {
        return new User(
            Uuid::v7(),
            Username::of(self::usernameOf($email)),
            Language::German,
            passwordHash: $passwordHash,
            status: AccountStatus::active(),
            registration: Registration::none(),
        );
    }

    /**
     * @param array<string, string|null> $columns the account's columns; `email` names the account in the test and
     *        gives its username, `display_name` is the name of its person record (see named())
     *
     * @return array<string, string|null>
     */
    public static function row(array $columns = []): array
    {
        $email = $columns['email'] ?? 'sara@example.org';

        return [
            'id' => Uuid::v7()->toRfc4122(),
            'username' => self::usernameOf($email),
            'person_id' => Uuid::v7()->toRfc4122(),
            'password_hash' => 'not-a-real-hash',
            'language' => 'de',
            'created_at' => '2026-11-08 12:00:00+01',
            ...array_diff_key($columns, self::HANDLES),
        ];
    }

    /**
     * @param array<string, string|null> $columns
     *
     * @return array<string, string|null> the columns, linked to a new person record when they give a `display_name`
     */
    public static function named(Connection $connection, array $columns): array
    {
        $name = $columns['display_name'] ?? null;

        if ($name === null || array_key_exists('person_id', $columns)) {
            return $columns;
        }

        return [...$columns, 'person_id' => self::person($connection, $name)];
    }

    public static function person(Connection $connection, string $displayName): string
    {
        $names = explode(' ', $displayName, limit: 2);
        $name = PersonName::of($names[0], $names[1] ?? $names[0], null);
        $id = Uuid::v7()->toRfc4122();

        $connection->insert('people.person', [
            'id' => $id,
            'status' => 'active',
            'first_name' => $name->firstName,
            'last_name' => $name->lastName,
            'name_key' => $name->key,
            'search_text' => $name->searchText,
            'created_at' => '2026-11-08 12:00:00+01',
        ]);

        return $id;
    }

    public static function usernameOf(string $email): string
    {
        $local = strtolower(explode('@', $email)[0]);
        $allowed = (string) preg_replace(['/[^a-z0-9._-]/', '/([._-])[._-]+/', '/^[^a-z]+/'], ['', '$1', ''], $local);
        $username = rtrim(substr($allowed, offset: 0, length: 32), characters: '._-');

        return strlen($username) < 3 ? 'user' . $username : $username;
    }
}
