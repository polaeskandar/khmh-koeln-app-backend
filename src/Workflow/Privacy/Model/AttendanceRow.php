<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Meetings\Domain\Entity\AttendanceMark;
use App\Context\Meetings\Domain\Entity\Session;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceRow
{
    public function __construct(
        public ?string $meeting,
        public ?string $date,
        public DateTimeImmutable $markedPresentAt,
        public ?DateTimeImmutable $removedAt,
        public ?string $removalNote,
    ) {
    }

    /**
     * @param ReadableCollection<int, AttendanceMark> $marks
     *
     * @return list<Uuid>
     */
    public static function sessionIdsOf(ReadableCollection $marks): array
    {
        return $marks->map(static fn (AttendanceMark $mark): Uuid => $mark->sessionId())->getValues();
    }

    /**
     * @param ReadableCollection<int, AttendanceMark> $marks
     * @param ReadableCollection<string, Session> $sessions
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $marks, ReadableCollection $sessions): array
    {
        return $marks->map(static function (AttendanceMark $mark) use ($sessions): self {
            $session = $sessions->get($mark->sessionId()->toRfc4122());
            $removal = $mark->removal();

            return new self(
                meeting: $session?->plan()->title,
                date: $session?->occurrenceDate()->toString(),
                markedPresentAt: $mark->recordedAt(),
                removedAt: $removal->at,
                removalNote: $removal->reason,
            );
        })->getValues();
    }
}
