<?php

declare(strict_types=1);

namespace App\Tests\Support\Context\People\Domain\Entity;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Tests\Support\Context\Identity\Domain\Entity\Accounts;
use App\Tests\Support\Context\Identity\Domain\Entity\RoleGrants;
use App\Tests\Support\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class Families
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, string|null> $columns
     */
    public function account(string $email, array $columns = [], string ...$roles): string
    {
        $id = Uuid::v7()->toRfc4122();

        $this
            ->database()
            ->connection()
            ->insert('identity.app_user', Accounts::row(['id' => $id, 'email' => $email, ...$columns]));

        new RoleGrants($this->container)->grant($id, ...$roles);

        return $id;
    }

    public function child(string $firstName = 'Kirollos'): Person
    {
        $child = new Person(PersonName::of($firstName, 'Iskander', null));
        $this->database()->persisted($child);

        return $child;
    }

    public function link(string $accountId, Person $child, string $status = 'approved'): void
    {
        $this
            ->database()
            ->connection()
            ->insert('people.guardian_relationship', [
                'id' => Uuid::v7()->toRfc4122(),
                'user_id' => $accountId,
                'person_id' => $child->id()->toRfc4122(),
                'relationship' => 'parent',
                'status' => $status,
                'approved_at' => '2026-11-01 12:00:00+01',
                'revoked_at' => $status === 'revoked' ? '2026-11-02 12:00:00+01' : null,
                'created_at' => '2026-11-01 12:00:00+01',
            ]);
    }

    private function database(): Database
    {
        return new Database($this->container);
    }
}
