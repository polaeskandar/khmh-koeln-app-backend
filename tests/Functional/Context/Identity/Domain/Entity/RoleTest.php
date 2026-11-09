<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Identity\Domain\Entity;

use App\Context\Identity\Domain\Entity\Permission;
use App\Context\Identity\Infrastructure\Persistence\Repository\DoctrineRoleRepository;
use App\Tests\Support\Context\Identity\Domain\Entity\Roles;
use App\Tests\Support\Database;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class RoleTest extends KernelTestCase
{
    /**
     * @param list<string> $permissions
     */
    #[DataProvider('defaults')]
    public function testTheDefaultRolesHaveThePrototypesRights(string $name, array $permissions): void
    {
        $role = $this->roles()->findByName($name);

        self::assertNotNull($role);

        self::assertEqualsCanonicalizing(
            $permissions,
            $role
                ->permissions()
                ->map(self::label(...))
                ->getValues(),
        );
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function defaults(): iterable
    {
        $servant = ['services:read', ...self::crud(['attendance', 'members', 'lessons', 'homework'])];

        $everything = [
            'users',
            'roles',
            'services',
            'stages',
            'sessions',
            'attendance',
            'members',
            'lessons',
            'homework',
        ];

        yield 'administrator' => ['administrator', self::crud($everything)];
        yield 'service leader' => [
            'Service Leader',
            [...$servant, 'services:update', ...self::crud(['stages', 'sessions'])],
        ];

        yield 'servant' => ['SERVANT', $servant];
        yield 'parent' => ['Parent', ['parentPortal:read']];
    }

    public function testRoleNamesAreUniqueIgnoringCaseAndSpacing(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);

        $this->roles()->save(Roles::named('  service   LEADER'));
    }

    /**
     * @param array<string, string> $columns
     */
    #[DataProvider('permissions')]
    public function testThePermissionCatalogIsClosed(array $columns, string $sqlState): void
    {
        $this->database()->assertRefused($sqlState, static fn (Connection $connection): int|string => $connection->insert('identity.permission', [
            'id' => Uuid::v7()->toRfc4122(),
            ...$columns,
        ]));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function permissions(): iterable
    {
        yield 'unknown resource' => [['resource' => 'rockets', 'action' => 'read'], Database::CHECK_VIOLATION];
        yield 'unknown action' => [['resource' => 'users', 'action' => 'launch'], Database::CHECK_VIOLATION];
        yield 'twice' => [['resource' => 'users', 'action' => 'read'], Database::UNIQUE_VIOLATION];
    }

    public function testDeletingARoleTakesItFromItsAccountsAndKeepsThePermissions(): void
    {
        $connection = $this->database()->connection();

        $connection->delete('identity.role', ['name_key' => 'servant']);

        self::assertSame(40, $connection->fetchOne('SELECT count(*) FROM identity.permission'));

        self::assertSame(
            0,
            $connection->fetchOne(
                "SELECT count(*) FROM identity.role_permission WHERE role_id = '019a3f00-0000-7000-8000-000000000003'",
            ),
        );
    }

    /**
     * @param list<string> $resources
     *
     * @return list<string>
     */
    private static function crud(array $resources): array
    {
        return array_merge(...array_map(static fn (string $resource): array => [
            $resource . ':create',
            $resource . ':read',
            $resource . ':update',
            $resource . ':delete',
        ], $resources));
    }

    private static function label(Permission $permission): string
    {
        return $permission->resource()->value . ':' . $permission->action()->value;
    }

    private function roles(): DoctrineRoleRepository
    {
        return new DoctrineRoleRepository($this->database()->entityManager());
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
