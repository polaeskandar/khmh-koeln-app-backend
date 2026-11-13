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
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStaffAccountRepository implements StaffAccountRepositoryInterface
{
    private const string NONE = '00000000-0000-0000-0000-000000000000';

    private const string LISTED = <<<'SQL'
        SELECT u.id::text AS id, u.username, u.person_id::text AS person, u.disabled_at IS NOT NULL AS disabled
        FROM identity.app_user u
        WHERE u.id IN (:staff) OR (u.age_check IS NULL AND u.id NOT IN (:holders))
        ORDER BY disabled, u.username
        LIMIT :limit
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $staff
     * @param list<Uuid> $holders
     * @param positive-int $limit
     *
     * @return list<array{id: string, username: string, personId: string, disabled: bool}>
     */
    #[Override]
    public function listed(array $staff, array $holders, int $limit): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::LISTED,
            ['staff' => self::idsOf($staff), 'holders' => self::idsOf($holders), 'limit' => $limit],
            [
                'staff' => ArrayParameterType::STRING,
                'holders' => ArrayParameterType::STRING,
                'limit' => ParameterType::INTEGER,
            ],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'username' => Type::of($row['username'] ?? null)->toString(),
            'personId' => Type::of($row['person'] ?? null)->toString(),
            'disabled' => ($row['disabled'] ?? false) === true,
        ])->getValues();
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return non-empty-list<string> the ids, and one that matches no account, so the list is never empty
     */
    private static function idsOf(array $ids): array
    {
        return [
            self::NONE,
            ...new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
        ];
    }
}
