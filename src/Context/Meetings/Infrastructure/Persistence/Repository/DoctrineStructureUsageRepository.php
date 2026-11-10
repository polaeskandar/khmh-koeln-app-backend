<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\Repository\StructureUsageRepositoryInterface;
use App\Context\Meetings\Domain\ValueObject\StructureImpact;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStructureUsageRepository implements StructureUsageRepositoryInterface
{
    private const string BY_SERVICE = <<<'SQL'
        SELECT sc.service_id::text AS owner,
               count(DISTINCT sc.id) FILTER (WHERE sc.active) AS schedules,
               count(se.id) FILTER (WHERE se.status = :scheduled AND se.occurrence_date >= :today) AS sessions
        FROM meetings.meeting_schedule sc
        LEFT JOIN meetings.meeting_session se ON se.schedule_id = sc.id
        GROUP BY sc.service_id
        SQL;

    private const string OF_STAGES = <<<'SQL'
        SELECT sc.stage_id::text AS owner,
               count(DISTINCT sc.id) FILTER (WHERE sc.active) AS schedules,
               count(se.id) FILTER (WHERE se.status = :scheduled AND se.occurrence_date >= :today) AS sessions
        FROM meetings.meeting_schedule sc
        LEFT JOIN meetings.meeting_session se ON se.schedule_id = sc.id
        WHERE sc.stage_id IN (:owners)
        GROUP BY sc.stage_id
        SQL;

    private const string UNFINISHED = <<<'SQL'
        SELECT status FROM meetings.meeting_session
        WHERE schedule_id IN (:schedules) AND status IN (:unfinished)
        ORDER BY id
        FOR UPDATE
        SQL;

    private const string LOCK_OWNED = <<<'SQL'
        SELECT id FROM meetings.meeting_schedule
        WHERE (CAST(:service AS uuid) IS NULL OR service_id = :service)
          AND (CAST(:stage AS uuid) IS NULL OR stage_id = :stage)
        ORDER BY id
        FOR UPDATE
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function listImpactByService(LocalDate $today): ReadableCollection
    {
        return $this->impactsFrom(
            self::BY_SERVICE,
            ['scheduled' => SessionStatus::Scheduled->value, 'today' => $today->toString()],
            types: [],
        );
    }

    /**
     * @param list<Uuid> $stageIds
     */
    #[Override]
    public function listImpactOfStages(array $stageIds, LocalDate $today): ReadableCollection
    {
        if ($stageIds === []) {
            return new ArrayCollection();
        }

        return $this->impactsFrom(
            self::OF_STAGES,
            [
                'owners' => self::strings($stageIds),
                'scheduled' => SessionStatus::Scheduled->value,
                'today' => $today->toString(),
            ],
            types: ['owners' => ArrayParameterType::STRING],
        );
    }

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<SessionStatus>
     */
    #[Override]
    public function lockUnfinished(array $scheduleIds): array
    {
        if ($scheduleIds === []) {
            return [];
        }

        $statuses = $this->entityManager->getConnection()->fetchFirstColumn(
            self::UNFINISHED,
            [
                'schedules' => self::strings($scheduleIds),
                'unfinished' => [SessionStatus::Scheduled->value, SessionStatus::Active->value],
            ],
            ['schedules' => ArrayParameterType::STRING, 'unfinished' => ArrayParameterType::STRING],
        );

        return new ArrayCollection($statuses)
            ->map(Type::of(...))
            ->map(static fn (Type $status): SessionStatus => SessionStatus::from($status->toString()))
            ->getValues();
    }

    #[Override]
    public function lockSchedulesOf(?Uuid $serviceId, ?Uuid $stageId): void
    {
        $this->entityManager->getConnection()->executeQuery(self::LOCK_OWNED, [
            'service' => $serviceId?->toRfc4122(),
            'stage' => $stageId?->toRfc4122(),
        ]);
    }

    /**
     * @param array<string, string|list<string>> $params
     * @param array<string, ArrayParameterType> $types
     *
     * @return ReadableCollection<string, StructureImpact>
     */
    private function impactsFrom(string $sql, array $params, array $types): ReadableCollection
    {
        $rows = new ArrayCollection($this->entityManager->getConnection()->fetchAllAssociative($sql, $params, $types));

        return Collections::firstPerKey($rows, keyOf: static fn (array $row): string => Type::of(
            $row['owner'] ?? null,
        )->toString())->map(static fn (array $row): StructureImpact => new StructureImpact(
            activeSchedules: Integers::of($row['schedules'] ?? null),
            upcomingSessions: Integers::of($row['sessions'] ?? null),
        ));
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<string>
     */
    private static function strings(array $ids): array
    {
        return new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();
    }
}
