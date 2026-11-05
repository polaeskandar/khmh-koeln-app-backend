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
        SELECT s.id::text AS id, 'cancelReason' AS "where", s.cancel_reason AS text,
               s.occurrence_date::text AS date, s.title AS meeting
        FROM meetings.meeting_session s
        WHERE s.schedule_id IN (:schedules) AND s.cancel_reason IS NOT NULL
        UNION ALL
        SELECT e.id::text, 'sessionNote', e.note, s.occurrence_date::text, s.title
        FROM meetings.session_event e JOIN meetings.meeting_session s ON s.id = e.session_id
        WHERE s.schedule_id IN (:schedules) AND e.note IS NOT NULL
        UNION ALL
        SELECT a.id::text, 'removalReason', a.removal_reason, s.occurrence_date::text, s.title
        FROM meetings.attendance a JOIN meetings.meeting_session s ON s.id = a.session_id
        WHERE s.schedule_id IN (:schedules) AND a.removal_reason IS NOT NULL AND a.person_id <> :person
        ORDER BY 4, 1
        SQL;

    private const array COLUMNS = [
        'cancelReason' => ['meetings.meeting_session', 'cancel_reason'],
        'sessionNote' => ['meetings.session_event', 'note'],
        'removalReason' => ['meetings.attendance', 'removal_reason'],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Uuid> $scheduleIds
     *
     * @return list<array{id: string, where: string, text: string, date: string, meeting: string}>
     */
    #[Override]
    public function notesOf(array $scheduleIds, Uuid $exceptPerson): array
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
                'person' => $exceptPerson->toRfc4122(),
            ],
            ['schedules' => ArrayParameterType::STRING],
        );

        return new ArrayCollection($rows)->map(static fn (array $row): array => [
            'id' => Type::of($row['id'] ?? null)->toString(),
            'where' => Type::of($row['where'] ?? null)->toString(),
            'text' => Type::of($row['text'] ?? null)->toString(),
            'date' => Type::of($row['date'] ?? null)->toString(),
            'meeting' => Type::of($row['meeting'] ?? null)->toString(),
        ])->getValues();
    }

    #[Override]
    public function clear(string $where, string $id): void
    {
        $target = self::COLUMNS[$where] ?? null;

        if ($target === null) {
            return;
        }

        $this->entityManager->getConnection()->update($target[0], [$target[1] => null], ['id' => $id]);
    }
}
