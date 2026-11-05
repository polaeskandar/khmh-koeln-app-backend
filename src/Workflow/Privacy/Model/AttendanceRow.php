<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Attendance\Domain\Entity\Attendance;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AttendanceRow
{
    public function __construct(
        public ?string $meeting,
        public ?string $date,
        public ?string $markedPresentAt,
        public ?string $removedAt,
        public ?string $removalNote,
    ) {
    }

    /**
     * @param ReadableCollection<int, Attendance> $marks
     *
     * @return list<self>
     */
    public static function listOf(ReadableCollection $marks, MeetingLookup $meetings, Calendar $calendar): array
    {
        $sessions = $meetings->sessions(
            $marks->map(static fn (Attendance $mark): Uuid => $mark->sessionId())->getValues(),
        );

        return $marks->map(static function (Attendance $mark) use ($sessions, $calendar): self {
            $session = $sessions->get($mark->sessionId()->toRfc4122());
            $removal = $mark->removal();

            return new self(
                meeting: $session?->details()->name,
                date: $session?->occurrenceDate()->toString(),
                markedPresentAt: ExportInstant::of($mark->recordedAt(), $calendar),
                removedAt: ExportInstant::of($removal->at, $calendar),
                removalNote: $removal->reason,
            );
        })->getValues();
    }
}
