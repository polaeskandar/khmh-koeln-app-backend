<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Workflow\Learning\Model\MeetingViews;
use App\Workflow\Learning\Model\NoMeeting;
use App\Workflow\Learning\Model\OffScheduleMeeting;
use App\Workflow\Learning\Model\PlannedMeeting;
use App\Workflow\Learning\Model\SessionMeeting;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class MeetingViewsTest extends KernelTestCase
{
    public function testEachLessonResolvesToItsMeeting(): void
    {
        $school = Structure::service('Sunday School');
        new Database(self::getContainer())->persisted($school);
        $schedules = new Schedules(self::getContainer());
        $sunday = $schedules->weekly($school, 'Sunday class', 7);
        $inactive = $schedules->weekly($school, 'Old class', 7);

        $schedules->session($sunday, '2026-11-08');
        $schedules->session($sunday, '2026-11-22', "status = 'canceled', cancel_reason = 'Holiday'");
        $database = new Database(self::getContainer());
        $database->connection()->executeStatement('UPDATE meetings.meeting_schedule SET active = false WHERE id = ?', [$inactive->id()->toRfc4122()]);
        $database->entityManager()->clear();

        $lessons = new ArrayCollection([
            $this->lesson($sunday->id(), '2026-11-15'),
            $this->lesson($sunday->id(), '2026-11-08'),
            $this->lesson($sunday->id(), '2026-11-22'),
            $this->lesson($inactive->id(), '2026-11-29'),
            $this->lesson(null, '2026-12-06'),
        ]);

        $found = $this->meetings()->listSchedules(MeetingViews::scheduleIdsOf($lessons));

        $sessions = $this->meetings()->listSessionsOn($found, MeetingViews::datesOf($lessons));
        $views = MeetingViews::of($found, $sessions, $lessons, ArchivedStructure::of([]));
        $archived = MeetingViews::of($found, $sessions, $lessons, ArchivedStructure::of([$school->id()]));

        self::assertSame(
            [
                PlannedMeeting::class,
                SessionMeeting::class,
                SessionMeeting::class,
                OffScheduleMeeting::class,
                NoMeeting::class,
            ],
            array_map(static fn (object $view): string => $view::class, $views),
        );

        $planned = $views[0] ?? null;
        $canceled = $views[2] ?? null;
        $offSchedule = $views[3] ?? null;
        self::assertInstanceOf(PlannedMeeting::class, $planned);
        self::assertInstanceOf(SessionMeeting::class, $canceled);
        self::assertInstanceOf(OffScheduleMeeting::class, $offSchedule);
        self::assertSame('2026-11-14', $planned->createdOn);
        self::assertSame(SessionStatus::Canceled, $canceled->progress->status);
        self::assertSame('Holiday', $canceled->progress->cancelReason);
        self::assertSame('Old class', $offSchedule->title);

        self::assertSame(
            [
                OffScheduleMeeting::class,
                SessionMeeting::class,
                SessionMeeting::class,
                OffScheduleMeeting::class,
                NoMeeting::class,
            ],
            array_map(static fn (object $view): string => $view::class, $archived),
        );
    }

    private function lesson(?Uuid $scheduleId, string $date): Lesson
    {
        return new Lesson(
            Uuid::v7(),
            new MeetingLink($scheduleId, LocalDate::of($date)),
            new LessonText('Lesson'),
            null,
        );
    }

    private function meetings(): MeetingLookup
    {
        $meetings = self::getContainer()->get(MeetingLookup::class);
        self::assertInstanceOf(MeetingLookup::class, $meetings);

        return $meetings;
    }
}
