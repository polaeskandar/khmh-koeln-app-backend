<?php

declare(strict_types=1);

namespace App\Context\Attendance\Infrastructure\Persistence\Repository;

use App\Context\Attendance\Domain\Repository\RemovalNoteRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineRemovalNoteRepository implements RemovalNoteRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $sessionIds
     *
     * @return list<array{id: string, text: string, sessionId: string}>
     */
    #[Override]
    public function notesOf(array $sessionIds, Uuid $exceptPerson): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $rows = $this->entityManager
            ->getConnection()
            ->createQueryBuilder()
            ->select('id::text AS id', 'removal_reason AS text', 'session_id::text AS session')
            ->from('attendance.attendance')
            ->where('session_id IN (:sessions)')
            ->andWhere('person_id <> :person')
            ->andWhere('removal_reason IS NOT NULL')
            ->setParameter('sessions', $sessionIds, ArrayParameterType::STRING)
            ->setParameter('person', $exceptPerson->toRfc4122())
            ->fetchAllAssociative();

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'text' => Type::of($row['text'] ?? null)->toString(),
            'sessionId' => Type::of($row['session'] ?? null)->toString(),
        ])->getValues();
    }

    #[Override]
    public function clear(string $id): void
    {
        $this->entityManager->getConnection()->update(
            'attendance.attendance',
            ['removal_reason' => null],
            ['id' => $id],
        );
    }
}
