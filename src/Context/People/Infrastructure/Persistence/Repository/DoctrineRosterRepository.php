<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Collections\Model\Groups;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRosterRepository implements RosterRepositoryInterface
{
    private const string CURRENT = 'm.starts_on <= :on AND (m.ends_on IS NULL OR m.ends_on >= :on)';

    private const string ROSTERS = <<<'SQL'
        SELECT k.key, p.id AS person_id
        FROM jsonb_to_recordset(CAST(:keys AS jsonb)) AS k(key text, service_id uuid, stage_id uuid, day date)
        JOIN people.person p ON EXISTS (
            SELECT 1 FROM people.membership m
            WHERE m.person_id = p.id AND m.service_id = k.service_id
              AND m.stage_id IS NOT DISTINCT FROM k.stage_id
              AND m.starts_on <= k.day AND (m.ends_on IS NULL OR m.ends_on >= k.day)
        )
        ORDER BY k.key, p.last_name COLLATE "de-x-icu", p.first_name COLLATE "de-x-icu", p.id
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function rosterOn(RosterKey $key): array
    {
        return $this->rostersOn([$key])->get($key->id()) ?? [];
    }

    /**
     * @param list<RosterKey> $keys
     */
    #[Override]
    public function rostersOn(array $keys): ReadableCollection
    {
        $wanted = Collections::firstPerKey(
            new ArrayCollection($keys),
            keyOf: static fn (RosterKey $key): string => $key->id(),
        );

        if ($wanted->isEmpty()) {
            return new ArrayCollection();
        }

        $rows = Groups::of(
            $this->rosterRows($wanted),
            keyOf: static fn (array $row): string => $row['key'],
            valueOf: static fn (array $row): Uuid => $row['personId'],
        );

        return $wanted->map(static fn (RosterKey $key): array => $rows->get($key->id()) ?? []);
    }

    /**
     * @param list<Uuid> $serviceIds
     */
    #[Override]
    public function countServiceMembers(array $serviceIds, LocalDate $on): ReadableCollection
    {
        if ($serviceIds === []) {
            return new ArrayCollection();
        }

        $rows = $this
            ->current($serviceIds, $on, column: 'm.service_id')
            ->addSelect('count(DISTINCT m.person_id) FILTER (WHERE m.stage_id IS NULL) AS service_level')
            ->executeQuery()
            ->fetchAllAssociative();

        return Collections::firstPerKey(new ArrayCollection($rows), keyOf: static fn (array $row): string => Type::of(
            $row['grouped'] ?? null,
        )->toString())->map(static fn (array $row): array => [
            'members' => Integers::of($row['members'] ?? null),
            'serviceLevel' => Integers::of($row['service_level'] ?? null),
        ]);
    }

    /**
     * @param list<Uuid> $stageIds
     */
    #[Override]
    public function countStageMembers(array $stageIds, LocalDate $on): ReadableCollection
    {
        if ($stageIds === []) {
            return new ArrayCollection();
        }

        $rows = $this->current($stageIds, $on, column: 'm.stage_id')->executeQuery()->fetchAllAssociative();

        return Collections::firstPerKey(new ArrayCollection($rows), keyOf: static fn (array $row): string => Type::of(
            $row['grouped'] ?? null,
        )->toString())->map(static fn (array $row): int => Integers::of($row['members'] ?? null));
    }

    /**
     * @param list<Uuid> $ids
     */
    private function current(array $ids, LocalDate $on, string $column): QueryBuilder
    {
        return $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select($column . ' AS grouped', 'count(DISTINCT m.person_id) AS members')
            ->from('people.membership', 'm')
            ->where($column . ' IN (:ids)')
            ->andWhere(self::CURRENT)
            ->groupBy($column)
            ->setParameter(
                'ids',
                new ArrayCollection($ids)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues(),
                ArrayParameterType::STRING,
            )
            ->setParameter('on', $on->toString());
    }

    /**
     * @param ReadableCollection<string, RosterKey> $keys
     *
     * @return ReadableCollection<int, array{key: string, personId: Uuid}>
     */
    private function rosterRows(ReadableCollection $keys): ReadableCollection
    {
        $rows = $this->entityManager
            ->getConnection()
            ->executeQuery(
                self::ROSTERS,
                ['keys' => $keys->map(self::recordOf(...))->getValues()],
                ['keys' => Types::JSON],
            )
            ->fetchAllAssociative();

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'key' => Type::of($row['key'] ?? null)->toString(),
            'personId' => Uuid::fromString(Type::of($row['person_id'] ?? null)->toString()),
        ]);
    }

    /**
     * @return array{key: string, service_id: string, stage_id: string|null, day: string}
     */
    private static function recordOf(RosterKey $key): array
    {
        return [
            'key' => $key->id(),
            'service_id' => $key->serviceId->toRfc4122(),
            'stage_id' => $key->stageId?->toRfc4122(),
            'day' => $key->day->toString(),
        ];
    }
}
