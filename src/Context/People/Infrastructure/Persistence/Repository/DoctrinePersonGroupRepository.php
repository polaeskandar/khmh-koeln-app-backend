<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Repository\PersonGroupRepositoryInterface;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonGroupRepository implements PersonGroupRepositoryInterface
{
    private const string OPEN = <<<'SQL'
        SELECT service_id::text AS service, stage_id::text AS stage FROM people.membership
        WHERE person_id = :person AND (ends_on IS NULL OR ends_on >= CAST(:day AS date))
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{serviceId: string, stageId: ?string}>
     */
    #[Override]
    public function openOn(Uuid $personId, LocalDate $day): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(self::OPEN, [
            'person' => $personId->toRfc4122(),
            'day' => $day->toString(),
        ]);

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'serviceId' => Type::of($row['service'] ?? null)->toString(),
            'stageId' => Type::of($row['stage'] ?? null)->toStringOrNull(),
        ])->getValues();
    }
}
