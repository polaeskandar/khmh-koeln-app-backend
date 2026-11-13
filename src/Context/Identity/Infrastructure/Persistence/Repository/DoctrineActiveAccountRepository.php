<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Repository;

use App\Context\Identity\Domain\Repository\ActiveAccountRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;

final readonly class DoctrineActiveAccountRepository implements ActiveAccountRepositoryInterface
{
    private const string LISTED = <<<'SQL'
        SELECT id::text AS id, username, person_id::text AS person
        FROM identity.app_user
        WHERE disabled_at IS NULL
        ORDER BY username
        LIMIT :limit
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, username: string, personId: string}>
     */
    #[Override]
    public function listed(int $limit): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::LISTED,
            ['limit' => $limit],
            ['limit' => ParameterType::INTEGER],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'username' => Type::of($row['username'] ?? null)->toString(),
            'personId' => Type::of($row['person'] ?? null)->toString(),
        ])->getValues();
    }
}
