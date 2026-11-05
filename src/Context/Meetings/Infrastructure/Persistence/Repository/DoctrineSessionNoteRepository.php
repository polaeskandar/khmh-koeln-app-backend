<?php

declare(strict_types=1);

namespace App\Context\Meetings\Infrastructure\Persistence\Repository;

use App\Context\Meetings\Domain\Repository\SessionNoteRepositoryInterface;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSessionNoteRepository implements SessionNoteRepositoryInterface
{
    private const string NOTES = <<<'SQL'
        SELECT s.id::text AS id, 'cancelReason' AS "where", s.cancel_reason AS text, s.occurrence_date::text AS date,
               COALESCE(s.title, m.name) AS meeting, s.id::text AS "sessionId"
        FROM meetings.meeting_session s JOIN meetings.meeting_schedule m ON m.id = s.schedule_id
        WHERE s.schedule_id IN (:schedules) AND s.cancel_reason IS NOT NULL
        UNION ALL
        SELECT e.id::text, 'sessionNote', e.note, s.occurrence_date::text, COALESCE(s.title, m.name), s.id::text
        FROM meetings.session_event e JOIN meetings.meeting_session s ON s.id = e.session_id
        JOIN meetings.meeting_schedule m ON m.id = s.schedule_id
        WHERE s.schedule_id IN (:schedules) AND e.note IS NOT NULL
        ORDER BY 4, 1
        SQL;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<array{id: string, where: string, text: string, date: string, meeting: string, sessionId: string}>
     */
    #[Override]
    public function notesOf(array $scheduleIds): array
    {
        if ($scheduleIds === []) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            self::NOTES,
            [
                'schedules' => new ArrayCollection($scheduleIds)->map(
                    static fn (Uuid $id): string => $id->toRfc4122(),
                )->getValues(),
            ],
            ['schedules' => ArrayParameterType::STRING],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'where' => Type::of($row['where'] ?? null)->toString(),
            'text' => Type::of($row['text'] ?? null)->toString(),
            'date' => Type::of($row['date'] ?? null)->toString(),
            'meeting' => Type::of($row['meeting'] ?? null)->toString(),
            'sessionId' => Type::of($row['sessionId'] ?? null)->toString(),
        ])->getValues();
    }

    #[Override]
    public function clear(string $where, string $id): void
    {
        $connection = $this->entityManager->getConnection();

        if ($where === 'cancelReason') {
            $connection->update('meetings.meeting_session', ['cancel_reason' => null], ['id' => $id]);

            return;
        }

        $connection->update('meetings.session_event', ['note' => null], ['id' => $id]);
    }
}
