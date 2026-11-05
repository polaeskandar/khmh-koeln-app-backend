<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Repository\RecentLessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\TaughtLesson;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRecentLessonRepository implements RecentLessonRepositoryInterface
{
    private const string LAST = <<<'SQL'
        SELECT DISTINCT ON (stage_id) stage_id::text AS stage, title, teaching_date::text AS date, passage, memory_verse
        FROM learning.lesson
        WHERE stage_id IN (:stages) AND status <> 'draft' AND archived_at IS NULL AND teaching_date <= :today
        ORDER BY stage_id, teaching_date DESC, id DESC
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return array<string, TaughtLesson>
     */
    #[Override]
    public function lastTaughtIn(array $stageIds, LocalDate $today): array
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::LAST,
            [
                'stages' => new ArrayCollection($stageIds)->map(
                    static fn (Uuid $id): string => $id->toRfc4122(),
                )->getValues(),
                'today' => $today->toString(),
            ],
            ['stages' => ArrayParameterType::STRING],
        );

        $byStage = Collections::firstPerKey(
            new ArrayCollection($rows),
            keyOf: static fn (array $row): string => Type::of($row['stage'] ?? null)->toString(),
        );

        return $byStage->map(static fn (array $row): TaughtLesson => new TaughtLesson(
            title: Type::of($row['title'] ?? null)->toString(),
            teachingDate: Type::of($row['date'] ?? null)->toString(),
            passage: Type::of($row['passage'] ?? null)->toStringOrNull(),
            memoryVerse: Type::of($row['memory_verse'] ?? null)->toStringOrNull(),
        ))->toArray();
    }
}
