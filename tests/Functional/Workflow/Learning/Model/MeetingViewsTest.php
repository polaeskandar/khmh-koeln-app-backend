<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Workflow\Learning\Model\MeetingView;
use App\Workflow\Learning\Model\MeetingViews;
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

        $found = $this->meetings()->schedules(MeetingViews::scheduleIdsOf($lessons));

        $sessions = $this->meetings()->sessionsOn($found, MeetingViews::datesOf($lessons));
        $views = MeetingViews::of($found, $sessions, $lessons, ArchivedStructure::of([]));
        $archived = MeetingViews::of($found, $sessions, $lessons, ArchivedStructure::of([$school->id()]));

        self::assertSame(
            ['planned', 'session', 'session', 'not-on-schedule', 'none'],
            array_map(static fn (MeetingView $view): string => $view->kind, $views),
        );

        self::assertSame('2026-11-14', $views[0]->members['createdOn'] ?? null);
        self::assertSame('canceled', $views[2]->members['status'] ?? null);
        self::assertSame('Holiday', $views[2]->members['cancelReason'] ?? null);
        self::assertSame('Old class', $views[3]->members['title'] ?? null);

        self::assertSame(
            ['not-on-schedule', 'session', 'session', 'not-on-schedule', 'none'],
            array_map(static fn (MeetingView $view): string => $view->kind, $archived),
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
