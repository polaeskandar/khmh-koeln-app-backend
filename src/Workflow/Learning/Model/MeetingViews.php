<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingViews
{
    /**
     * @param ReadableCollection<non-empty-string, Schedule> $schedules
     * @param ReadableCollection<non-empty-string, Session> $sessions
     */
    private function __construct(
        private ReadableCollection $schedules,
        private ReadableCollection $sessions,
    ) {
    }

    /**
     * @param ReadableCollection<int, Lesson> $lessons
     *
     * @return list<MeetingView>
     */
    public static function lookedUpIn(MeetingLookup $meetings, ReadableCollection $lessons): array
    {
        $scheduleIds = Collections::mapPresent(
            $lessons,
            static fn (Lesson $lesson): ?Uuid => $lesson->link()->scheduleId,
        );

        $schedules = $meetings->schedules($scheduleIds->getValues());
        $dates = $lessons->map(static fn (Lesson $lesson): LocalDate => $lesson->link()->teachingDate)->getValues();

        $views = new self(
            Collections::firstPerKey($schedules, keyOf: self::scheduleKey(...)),
            Collections::firstPerKey($meetings->sessionsOn($schedules, $dates), keyOf: self::sessionKey(...)),
        );

        return $lessons->map($views->viewOf(...))->getValues();
    }

    private function viewOf(Lesson $lesson): MeetingView
    {
        $link = $lesson->link();

        if ($link->scheduleId === null) {
            return MeetingView::none($link->teachingDate);
        }

        $session = $this->sessions->get(self::key($link->scheduleId, $link->teachingDate));

        return $session === null
            ? MeetingView::forSchedule($this->schedules->get($link->scheduleId->toRfc4122()), $link->teachingDate)
            : MeetingView::session($session);
    }

    /**
     * @return non-empty-string
     */
    private static function scheduleKey(Schedule $schedule): string
    {
        return $schedule->id()->toRfc4122();
    }

    /**
     * @return non-empty-string
     */
    private static function sessionKey(Session $session): string
    {
        return self::key($session->schedule()->id(), $session->occurrenceDate());
    }

    /**
     * @return non-empty-string
     */
    private static function key(Uuid $scheduleId, LocalDate $date): string
    {
        return $scheduleId->toRfc4122() . '|' . $date->toString();
    }
}
