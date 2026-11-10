<?php

declare(strict_types=1);

namespace App\Context\People\Infrastructure\Persistence\Repository;

use App\Context\People\Domain\Repository\PersonListRepositoryInterface;
use App\Context\People\Domain\ValueObject\CoveredRosters;
use App\Context\People\Domain\ValueObject\PersonFilter;
use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Integers;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePersonListRepository implements PersonListRepositoryInterface
{
    private const string STATUS = 'p.status = COALESCE(CAST(:status AS text), p.status)';

    private const string IN_ROSTER = <<<'SQL'
        (CAST(:service AS uuid) IS NULL OR EXISTS (
          SELECT 1 FROM people.membership m WHERE m.person_id = p.id AND m.service_id = CAST(:service AS uuid)
            AND m.stage_id IS NOT DISTINCT FROM COALESCE(CAST(:stage AS uuid), m.stage_id)
            AND m.starts_on <= CAST(:today AS date) AND (m.ends_on IS NULL OR m.ends_on >= CAST(:today AS date))))
        SQL;

    private const string COVERED = <<<'SQL'
        EXISTS (
          SELECT 1 FROM people.membership c WHERE c.person_id = p.id
            AND (c.ends_on IS NULL OR c.ends_on >= CAST(:today AS date))
            AND (c.service_id::text IN (:coveredServices) OR c.stage_id::text IN (:coveredStages)))
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<Uuid>
     */
    #[Override]
    public function page(PersonFilter $filter, CoveredRosters $covered, int $offset, int $limit): array
    {
        $ids = $this
            ->matching($filter, $covered)
            ->select('p.id')
            ->orderBy('p.last_name COLLATE "de-x-icu"')
            ->addOrderBy('p.first_name COLLATE "de-x-icu"')
            ->addOrderBy('p.id')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchFirstColumn();

        return new ArrayCollection($ids)
            ->map(Type::of(...))
            ->map(static fn (Type $id): Uuid => Uuid::fromString($id->toString()))
            ->getValues();
    }

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    #[Override]
    public function byName(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $ordered = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('p.id')
            ->from('people.person', 'p')
            ->where('p.id IN (:ids)')
            ->orderBy('p.last_name COLLATE "de-x-icu"')
            ->addOrderBy('p.first_name COLLATE "de-x-icu"')
            ->addOrderBy('p.id')
            ->setParameter('ids', self::strings($ids), ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchFirstColumn();

        return new ArrayCollection($ordered)
            ->map(Type::of(...))
            ->map(static fn (Type $id): Uuid => Uuid::fromString($id->toString()))
            ->getValues();
    }

    #[Override]
    public function count(PersonFilter $filter, CoveredRosters $covered): int
    {
        return Integers::of($this->matching($filter, $covered)->select('count(*)')->executeQuery()->fetchOne());
    }

    private function matching(PersonFilter $filter, CoveredRosters $covered): QueryBuilder
    {
        $query = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->from('people.person', 'p')
            ->where(self::STATUS)
            ->andWhere(self::IN_ROSTER)
            ->setParameter('status', $filter->status?->value)
            ->setParameter('service', $filter->serviceId?->toRfc4122())
            ->setParameter('stage', $filter->stageId?->toRfc4122())
            ->setParameter('today', $filter->today->toString());

        self::limitTo($query, $covered);

        foreach ($filter->words as $index => $word) {
            $query->andWhere(Text::format("p.search_text LIKE :word%d ESCAPE '!'", $index)->toString())->setParameter(
                'word' . $index,
                '%' . Text::of($word)->replaceMatches('/([!%_])/', replacement: '!$1')->toString() . '%',
            );
        }

        return $query;
    }

    private static function limitTo(QueryBuilder $query, CoveredRosters $covered): void
    {
        if ($covered->serviceIds === null) {
            return;
        }

        $query
            ->andWhere(self::COVERED)
            ->setParameter('coveredServices', $covered->serviceIds, ArrayParameterType::STRING)
            ->setParameter('coveredStages', $covered->stageIds ?? [], ArrayParameterType::STRING);
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
