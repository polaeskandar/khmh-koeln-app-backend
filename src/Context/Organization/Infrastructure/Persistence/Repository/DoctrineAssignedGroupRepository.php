<?php

declare(strict_types=1);

namespace App\Context\Organization\Infrastructure\Persistence\Repository;

use App\Context\Organization\Domain\Repository\AssignedGroupRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAssignedGroupRepository implements AssignedGroupRepositoryInterface
{
    private const string GROUPS = <<<'SQL'
        SELECT service_id::text AS service, stage_id::text AS stage
        FROM organization.staff_assignment WHERE account_id = ?
        SQL;

    private const string COVERING = <<<'SQL'
        SELECT DISTINCT account_id::text FROM organization.staff_assignment
        WHERE service_id = :service AND (stage_id IS NULL OR stage_id = :stage)
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{serviceId: string, stageId: ?string}>
     */
    #[Override]
    public function groupsOf(Uuid $accountId): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(self::GROUPS, [$accountId->toRfc4122()]);

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'serviceId' => Type::of($row['service'] ?? null)->toString(),
            'stageId' => Type::of($row['stage'] ?? null)->toStringOrNull(),
        ])->getValues();
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function accountsCovering(Uuid $serviceId, ?Uuid $stageId): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(self::COVERING, [
            'service' => $serviceId->toRfc4122(),
            'stage' => $stageId?->toRfc4122(),
        ]);

        return new ArrayCollection($ids)->map(static fn (mixed $id): string => Type::of($id)->toString())->getValues();
    }
}
