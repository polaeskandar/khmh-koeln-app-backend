<?php

declare(strict_types=1);

namespace App\Context\Learning\Infrastructure\Persistence\Repository;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonCollaboratorRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\LessonHelpers;
use App\Library\Collections\Model\Collections;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLessonCollaboratorRepository implements LessonCollaboratorRepositoryInterface
{
    private const string OF_LESSONS = <<<'SQL'
        SELECT lesson_id::text AS lesson, user_id::text AS account
        FROM learning.lesson_collaborator WHERE lesson_id IN (:lessons)
        SQL;

    private const string LOCK_LESSON = 'SELECT id FROM learning.lesson WHERE id = ? FOR UPDATE';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $lessonIds
     */
    #[Override]
    public function ofLessons(array $lessonIds): LessonHelpers
    {
        if ($lessonIds === []) {
            return new LessonHelpers();
        }

        $ids = new ArrayCollection($lessonIds)->map(static fn (Uuid $id): string => $id->toRfc4122())->getValues();

        $all = new ArrayCollection($this->entityManager->getConnection()->fetchAllAssociative(
            self::OF_LESSONS,
            ['lessons' => $ids],
            ['lessons' => ArrayParameterType::STRING],
        ));

        $lessonOf = static fn (array $row): string => Type::of($row['lesson'] ?? null)->toString();

        return new LessonHelpers(
            Collections::firstPerKey($all, keyOf: $lessonOf)->map(
                static fn (array $first): array => $all
                    ->filter(static fn (array $row): bool => $lessonOf($row) === $lessonOf($first))
                    ->map(static fn (array $row): string => Type::of($row['account'] ?? null)->toString())
                    ->getValues(),
            )->toArray(),
        );
    }

    /**
     * @param list<Uuid> $accountIds
     */
    #[Override]
    public function replace(Lesson $lesson, array $accountIds): void
    {
        $connection = $this->entityManager->getConnection();
        $lessonId = $lesson->id()->toRfc4122();
        $connection->executeQuery(self::LOCK_LESSON, [$lessonId]);
        $connection->delete('learning.lesson_collaborator', ['lesson_id' => $lessonId]);

        foreach ($accountIds as $accountId) {
            $connection->insert('learning.lesson_collaborator', [
                'lesson_id' => $lessonId,
                'user_id' => $accountId->toRfc4122(),
            ]);
        }
    }
}
