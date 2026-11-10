<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Entity\Role;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Identity\Domain\Enum\RoleScope;
use App\Context\Identity\Domain\Repository\RoleHolderRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineRoleHolderRepository implements RoleHolderRepositoryInterface
{
    private const string HOLDERS = <<<'DQL'
        SELECT u FROM App\Context\Identity\Domain\Entity\User u
        JOIN u.roles r
        WHERE r.name.key = :key
          AND u.status.approvedAt IS NOT NULL
          AND u.status.disabledAt IS NULL
        ORDER BY u.profile.displayName.value, u.id
        DQL;

    private const string HOLDERS_IN = <<<'DQL'
        SELECT DISTINCT u FROM App\Context\Identity\Domain\Entity\User u
        JOIN u.roles r
        WHERE r.scope = :scope
          AND u.status.approvedAt IS NOT NULL
          AND u.status.disabledAt IS NULL
        DQL;

    private const string HOLDERS_OF = <<<'DQL'
        SELECT u, LOWER(u.profile.displayName.value) AS HIDDEN sortName
        FROM App\Context\Identity\Domain\Entity\User u
        JOIN u.roles r
        WHERE r = :role
        ORDER BY sortName, u.id
        DQL;

    private const string COUNTS = <<<'SQL'
        SELECT role_id::text AS role, count(*) AS holders FROM identity.user_role GROUP BY role_id
        SQL;

    private const string ACTIVE_WITHOUT = <<<'SQL'
        SELECT 1
        FROM identity.app_user u
        JOIN identity.user_role ur ON ur.user_id = u.id
        JOIN identity.role_permission rp ON rp.role_id = ur.role_id
        JOIN identity.permission p ON p.id = rp.permission_id
        WHERE ur.role_id <> :role
          AND u.approved_at IS NOT NULL
          AND u.disabled_at IS NULL
          AND u.erases_on IS NULL
          AND p.action = :action
          AND p.resource = :resource
        LIMIT 1
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function activeHolders(string $roleNameKey): ReadableCollection
    {
        $query = $this->entityManager->createQuery(self::HOLDERS)->setParameter('key', $roleNameKey);

        return new ArrayCollection(ListOf::instances($query->getResult(), User::class));
    }

    #[Override]
    public function activeHoldersIn(RoleScope $scope): ReadableCollection
    {
        $query = $this->entityManager->createQuery(self::HOLDERS_IN)->setParameter('scope', $scope);

        return new ArrayCollection(ListOf::instances($query->getResult(), User::class));
    }

    #[Override]
    public function listHoldersOf(Role $role): ReadableCollection
    {
        $query = $this->entityManager->createQuery(self::HOLDERS_OF)->setParameter('role', $role);

        return new ArrayCollection(ListOf::instances($query->getResult(), User::class));
    }

    /**
     * @return array<string, int>
     */
    #[Override]
    public function countHolders(): array
    {
        $rows = new ArrayCollection(
            $this->entityManager->getConnection()->fetchAllAssociative(self::COUNTS),
        )->map(static fn (array $row): array => [
            'role' => Type::of($row['role'] ?? null)->toString(),
            'holders' => Integers::of($row['holders'] ?? null),
        ]);

        return Collections::firstPerKey($rows, keyOf: static fn (array $row): string => $row['role'])->map(
            static fn (array $row): int => $row['holders'],
        )->toArray();
    }

    #[Override]
    public function hasActiveAccountThatCanWithout(Role $role, Action $action, Resource $resource): bool
    {
        return $this->entityManager->getConnection()->fetchOne(self::ACTIVE_WITHOUT, [
            'role' => $role->id()->toRfc4122(),
            'action' => $action->value,
            'resource' => $resource->value,
        ]) !== false;
    }
}
