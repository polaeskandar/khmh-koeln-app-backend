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
        SELECT id::text AS id, display_name AS name, email
        FROM identity.app_user
        WHERE approved_at IS NOT NULL AND disabled_at IS NULL
        ORDER BY lower(display_name), id
        LIMIT :limit
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param positive-int $limit
     *
     * @return list<array{id: string, name: string, email: string}>
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
            'name' => Type::of($row['name'] ?? null)->toString(),
            'email' => Type::of($row['email'] ?? null)->toString(),
        ])->getValues();
    }
}
