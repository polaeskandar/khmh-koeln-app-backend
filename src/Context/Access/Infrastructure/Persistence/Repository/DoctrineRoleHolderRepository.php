<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Persistence\Repository;

use App\Context\Access\Domain\Entity\AccountRole;
use App\Context\Access\Domain\Entity\Role;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Access\Domain\Enum\RoleScope;
use App\Context\Access\Domain\Repository\RoleHolderRepositoryInterface;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\ListOf;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRoleHolderRepository implements RoleHolderRepositoryInterface
{
    private const string NONE = '00000000-0000-0000-0000-000000000000';

    private const string HOLDERS_OF = <<<'SQL'
        SELECT user_id::text FROM access.user_role WHERE role_id = :role ORDER BY user_id
        SQL;

    private const string HOLDERS_OF_KEY = <<<'SQL'
        SELECT DISTINCT ur.user_id::text
        FROM access.user_role ur JOIN access.role r ON r.id = ur.role_id
        WHERE r.name_key = :key
        SQL;

    private const string HOLDERS_IN = <<<'SQL'
        SELECT DISTINCT ur.user_id::text
        FROM access.user_role ur JOIN access.role r ON r.id = ur.role_id
        WHERE r.scope = :scope
        SQL;

    private const string HOLDERS_THAT_CAN = <<<'SQL'
        SELECT DISTINCT ur.user_id::text
        FROM access.user_role ur
        JOIN access.role_permission rp ON rp.role_id = ur.role_id
        JOIN access.permission p ON p.id = rp.permission_id
        WHERE p.action = :action AND p.resource = :resource AND ur.role_id <> :without
        SQL;

    private const string HOLDERS_BEYOND = <<<'SQL'
        SELECT DISTINCT ur.user_id::text
        FROM access.user_role ur
        JOIN access.role_permission rp ON rp.role_id = ur.role_id
        JOIN access.permission p ON p.id = rp.permission_id
        WHERE p.resource <> :resource
        SQL;

    private const string HOLDERS_ON = <<<'SQL'
        SELECT DISTINCT ur.user_id::text
        FROM access.user_role ur
        JOIN access.role_permission rp ON rp.role_id = ur.role_id
        JOIN access.permission p ON p.id = rp.permission_id
        WHERE p.resource = :resource
        SQL;

    private const string ALL_HOLDERS = 'SELECT DISTINCT user_id::text FROM access.user_role';

    private const string COUNTS = <<<'SQL'
        SELECT role_id::text AS role, count(*) AS holders FROM access.user_role GROUP BY role_id
        SQL;

    private const string ROLES_OF = <<<'DQL'
        SELECT ar, r, p FROM App\Context\Access\Domain\Entity\AccountRole ar
        JOIN ar.role r
        LEFT JOIN r.permissions p
        WHERE ar.accountId IN (:accounts)
        ORDER BY r.name.key
        DQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function listHoldersOf(Role $role): array
    {
        return $this->ids(self::HOLDERS_OF, ['role' => $role->id()->toRfc4122()]);
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

    /**
     * @return list<Role>
     */
    #[Override]
    public function listRolesOf(Uuid $accountId): array
    {
        return $this->listRolesOfAccounts([$accountId])[$accountId->toRfc4122()] ?? [];
    }

    /**
     * @param list<Uuid> $accountIds
     *
     * @return array<string, list<Role>>
     */
    #[Override]
    public function listRolesOfAccounts(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $byAccount = [];

        foreach ($this->held($accountIds) as $accountRole) {
            $byAccount[$accountRole->accountId()->toRfc4122()][] = $accountRole->role();
        }

        return $byAccount;
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function holdersOfKey(string $roleNameKey): array
    {
        return $this->ids(self::HOLDERS_OF_KEY, ['key' => $roleNameKey]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function holdersIn(RoleScope $scope): array
    {
        return $this->ids(self::HOLDERS_IN, ['scope' => $scope->value]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function holdersThatCan(Action $action, Resource $resource, ?Role $without = null): array
    {
        return $this->ids(self::HOLDERS_THAT_CAN, [
            'action' => $action->value,
            'resource' => $resource->value,
            'without' => $without?->id()->toRfc4122() ?? self::NONE,
        ]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function holdersOn(Resource $resource): array
    {
        return $this->ids(self::HOLDERS_ON, ['resource' => $resource->value]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function holdersBeyond(Resource $resource): array
    {
        return $this->ids(self::HOLDERS_BEYOND, ['resource' => $resource->value]);
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function allHolders(): array
    {
        return $this->ids(self::ALL_HOLDERS, []);
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<Uuid>
     */
    private function ids(string $sql, array $params): array
    {
        return new ArrayCollection($this->entityManager->getConnection()->fetchFirstColumn($sql, $params))->map(
            static fn (mixed $id): Uuid => Uuid::fromString(Type::of($id)->toString()),
        )->getValues();
    }

    /**
     * @param non-empty-list<Uuid> $accountIds
     *
     * @return list<AccountRole> with their roles and permissions, by role name
     */
    private function held(array $accountIds): array
    {
        $ids = new ArrayCollection($accountIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();

        return ListOf::instances(
            $this->entityManager
                ->createQuery(self::ROLES_OF)
                ->setParameter('accounts', $ids, ArrayParameterType::STRING)
                ->getResult(),
            AccountRole::class,
        );
    }
}
