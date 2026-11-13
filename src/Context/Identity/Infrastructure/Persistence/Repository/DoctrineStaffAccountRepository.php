<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Repository\StaffAccountRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineStaffAccountRepository implements StaffAccountRepositoryInterface
{
    private const string LISTED = <<<'SQL'
        SELECT u.id::text AS id, u.display_name AS name, u.email, u.disabled_at IS NOT NULL AS disabled
        FROM identity.app_user u
        WHERE (u.age_check IS NULL AND NOT EXISTS (SELECT 1 FROM identity.user_role ur WHERE ur.user_id = u.id))
          OR EXISTS (
            SELECT 1 FROM identity.user_role ur
            JOIN identity.role_permission rp ON rp.role_id = ur.role_id
            JOIN identity.permission p ON p.id = rp.permission_id
            WHERE ur.user_id = u.id AND p.resource <> 'parentPortal'
          )
        ORDER BY disabled, lower(u.display_name), u.id
        LIMIT :limit
        SQL;

    private const string ROLES = <<<'SQL'
        SELECT ur.user_id::text AS account, r.id::text AS id, r.name
        FROM identity.user_role ur JOIN identity.role r ON r.id = ur.role_id
        WHERE ur.user_id IN (:accounts)
        ORDER BY r.name_key
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, displayName: string, email: string, disabled: bool}>
     */
    #[Override]
    public function listed(int $limit): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::LISTED,
            ['limit' => $limit],
            [
                'limit' => ParameterType::INTEGER,
            ],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'displayName' => Type::of($row['name'] ?? null)->toString(),
            'email' => Type::of($row['email'] ?? null)->toString(),
            'disabled' => ($row['disabled'] ?? false) === true,
        ])->getValues();
    }

    /**
     * @param list<string> $accountIds
     *
     * @return list<array{accountId: string, id: string, name: string}>
     */
    #[Override]
    public function rolesOf(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::ROLES,
            ['accounts' => $accountIds],
            ['accounts' => ArrayParameterType::STRING],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'accountId' => Type::of($row['account'] ?? null)->toString(),
            'id' => Type::of($row['id'] ?? null)->toString(),
            'name' => Type::of($row['name'] ?? null)->toString(),
        ])->getValues();
    }
}
