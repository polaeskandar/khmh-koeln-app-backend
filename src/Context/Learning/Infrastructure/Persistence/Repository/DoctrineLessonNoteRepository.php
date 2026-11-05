<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Repository\LessonNoteRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLessonNoteRepository implements LessonNoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $stageIds
     *
     * @return list<array{id: string, text: string, date: string, stageId: string}>
     */
    #[Override]
    public function notesOf(array $stageIds): array
    {
        if ($stageIds === []) {
            return [];
        }

        $ids = new ArrayCollection($stageIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();

        $rows = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('id::text AS id', 'notes AS text', 'teaching_date::text AS date', 'stage_id::text AS stage')
            ->from('learning.lesson')
            ->where('stage_id IN (:stages)')
            ->andWhere('notes IS NOT NULL')
            ->setParameter('stages', $ids, ArrayParameterType::STRING)
            ->fetchAllAssociative();

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'text' => Type::of($row['text'] ?? null)->toString(),
            'date' => Type::of($row['date'] ?? null)->toString(),
            'stageId' => Type::of($row['stage'] ?? null)->toString(),
        ])->getValues();
    }

    #[Override]
    public function clear(string $id): void
    {
        $this->entityManager->getConnection()->update('learning.lesson', ['notes' => null], ['id' => $id]);
    }
}
