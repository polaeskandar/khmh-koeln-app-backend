<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\Identity\Infrastructure\Security;

use App\Context\Identity\Infrastructure\Security\SecurityUser;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Database;
use PHPUnit\Framework\Assert;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SignInAccounts
{
    public const string TYPED = 'correct horse battery';

    public const string MISTYPED = 'correct horse staple';

    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function add(string $email, array $columns = [], #[SensitiveParameter] string $secret = self::TYPED): string
    {
        $factory = $this->container->get(PasswordHasherFactoryInterface::class);
        Assert::assertInstanceOf(PasswordHasherFactoryInterface::class, $factory);
        $id = Uuid::v7()->toRfc4122();

        new Database($this->container)
            ->connection()
            ->insert('identity.app_user', Accounts::row([
                'id' => $id,
                'email' => $email,
                'password_hash' => $factory->getPasswordHasher(SecurityUser::class)->hash($secret),
                ...$columns,
            ]));

        return $id;
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function addWithPerson(string $email, array $columns = []): string
    {
        $displayName = $columns['display_name'] ?? 'Sara Hanna';

        return $this->add($email, [
            ...$columns,
            'person_id' => $this->person(new Database($this->container), $displayName),
        ]);
    }

    private function person(Database $database, string $displayName): string
    {
        $names = explode(' ', $displayName, limit: 2);
        $name = PersonName::of($names[0], $names[1] ?? $names[0], null);
        $id = Uuid::v7()->toRfc4122();

        $database->connection()->insert('people.person', [
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
}
