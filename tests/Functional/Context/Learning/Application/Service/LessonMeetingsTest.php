<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Application\Service;

use App\Context\Learning\Application\Service\LessonMeetings;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Workflow\Learning\Model\OfferedMeetings;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class LessonMeetingsTest extends KernelTestCase
{
    /**
     * @param array<string, string> $params
     */
    #[DataProvider('refusals')]
    public function testEachBrokenLinkIsReportedOnTheMeeting(
        string $kind,
        ?string $date,
        string $code,
        array $params,
    ): void {
        [$third, $sunday, $first] = $this->structure();

        $input = match ($kind) {
            'empty' => new MeetingChoice(),
            'other stage' => new MeetingChoice(scheduleId: $first->id()->toRfc4122(), date: '2026-11-15'),
            default => new MeetingChoice(scheduleId: $sunday->id()->toRfc4122(), date: $date),
        };

        $this->assertRefused($code, $params, fn () => $this->link($third->id(), $input));
    }

    /**
     * @return iterable<string, array{string, ?string, string, array<string, string>}>
     */
    public static function refusals(): iterable
    {
        yield 'nothing chosen' => ['empty', null, 'lessons.chooseWhen', []];
        yield 'another stage' => ['other stage', null, 'lessons.chooseMeeting', []];
        yield 'impossible date' => ['date', '2026-02-30', 'lessons.chooseDate', []];
        yield 'no meeting that day' => ['date', '2026-11-16', 'lessons.noMeetingThatDay', ['name' => 'Sunday class']];
    }

    public function testASessionBecomesItsScheduleAndDateAndBlocksASecondLesson(): void
    {
        [$third, $sunday] = $this->structure();
        $session = new Schedules(self::getContainer())->session($sunday, '2026-11-15');

        $link = $this->link($third->id(), new MeetingChoice(sessionId: $session->id()->toRfc4122()));
        self::assertTrue($sunday->id()->equals($link->scheduleId));
        self::assertSame('2026-11-15', $link->teachingDate->toString());

        $lesson = new Lesson($third->id(), $link, new LessonText('The Good Samaritan'), null);
        $archived = new Lesson($third->id(), $link, new LessonText('Old plan'), null);
        $archived->archive(new DateTimeImmutable('2026-11-01T12:00:00+01:00'));
        new Database(self::getContainer())->persisted($archived, $lesson);

        $input = new MeetingChoice(scheduleId: $sunday->id()->toRfc4122(), date: '2026-11-15');

        $this->assertRefused(
            'lessons.meetingHasLesson',
            ['title' => 'The Good Samaritan'],
            fn () => $this->link($third->id(), $input),
        );

        self::assertEquals($link, $this->link($third->id(), $input, editing: $lesson));
    }

    public function testACanceledSessionIsRefusedInEitherFormUnlessTheEditedLessonKeepsIt(): void
    {
        [$third, $sunday] = $this->structure();

        $canceled = new Schedules(self::getContainer())->session(
            $sunday,
            '2026-11-15',
            "status = 'canceled', cancel_reason = 'Holiday'",
        );

        $bySession = new MeetingChoice(sessionId: $canceled->id()->toRfc4122());
        $bySchedule = new MeetingChoice(scheduleId: $sunday->id()->toRfc4122(), date: '2026-11-15');

        $this->assertRefused('lessons.chooseMeeting', [], fn () => $this->link($third->id(), $bySession));
        $this->assertRefused('lessons.chooseMeeting', [], fn () => $this->link($third->id(), $bySchedule));

        $kept = new MeetingLink($sunday->id(), LocalDate::of('2026-11-15'));
        $lesson = new Lesson($third->id(), $kept, new LessonText('Kept on the canceled day'), null);
        new Database(self::getContainer())->persisted($lesson);

        self::assertEquals($kept, $this->link($third->id(), $bySession, editing: $lesson));
        self::assertEquals($kept, $this->link($third->id(), $bySchedule, editing: $lesson));
    }

    public function testAnArchivedServiceDoesNotMeetButAnUnchangedLinkStaysValid(): void
    {
        [$third, $sunday] = $this->structure();
        $planned = new MeetingChoice(scheduleId: $sunday->id()->toRfc4122(), date: '2026-11-15');
        $monday = new MeetingChoice(scheduleId: $sunday->id()->toRfc4122(), date: '2026-11-16');
        $archived = [$sunday->roster()->serviceId];

        $this->assertRefused(
            'lessons.noMeetingThatDay',
            ['name' => 'Sunday class'],
            fn () => $this->link($third->id(), $planned, archived: $archived),
        );

        $kept = new MeetingLink($sunday->id(), LocalDate::of('2026-11-16'));
        $lesson = new Lesson($third->id(), $kept, new LessonText('Kept after the schedule changed'), null);
        new Database(self::getContainer())->persisted($lesson);

        self::assertEquals($kept, $this->link($third->id(), $monday, editing: $lesson));
    }

    public function testASessionIdDoesNotExcuseADayWithoutAMeeting(): void
    {
        [$third, $sunday] = $this->structure();
        $lessons = self::getContainer()->get(LessonMeetings::class);
        self::assertInstanceOf(LessonMeetings::class, $lessons);
        $meetings = self::getContainer()->get(MeetingLookup::class);
        self::assertInstanceOf(MeetingLookup::class, $meetings);
        $monday = LocalDate::of('2026-11-16');

        $mixed = new MeetingChoice(
            scheduleId: $sunday->id()->toRfc4122(),
            sessionId: Uuid::v7()->toRfc4122(),
            date: $monday->toString(),
        );

        $offered = OfferedMeetings::of($meetings->slotOfSchedule($sunday->id(), $monday, ArchivedStructure::of([])));

        $this->assertRefused(
            'lessons.noMeetingThatDay',
            ['name' => 'Sunday class'],
            static fn () => $lessons->chosenLink($third->id(), $mixed, $offered),
        );
    }

    /**
     * @return array{Stage, Schedule, Schedule}
     */
    private function structure(): array
    {
        $school = Structure::service('Sunday School');
        $third = Structure::stage($school, '3rd Class', position: 2);
        $firstClass = Structure::stage($school, '1st Class', position: 0);
        new Database(self::getContainer())->persisted($school, $third, $firstClass);
        $schedules = new Schedules(self::getContainer());

        return [
            $third,
            $schedules->weekly($school, 'Sunday class', 7, stage: $third),
            $schedules->weekly($school, '1st Class Sunday', 7, stage: $firstClass),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private function assertRefused(string $code, array $params, callable $validate): void
    {
        try {
            $validate();
        } catch (InvalidInput $invalid) {
            $expected = $params === [] ? ['code' => $code] : ['code' => $code, 'params' => $params];
            self::assertSame(['meeting' => $expected], $invalid->fieldErrors);

            return;
        }

        self::fail('Expected ' . $code);
    }

    /**
     * @param list<Uuid> $archived
     */
    private function link(
        Uuid $stageId,
        MeetingChoice $choice,
        ?Lesson $editing = null,
        array $archived = [],
    ): MeetingLink {
        $meetings = self::getContainer()->get(MeetingLookup::class);
        self::assertInstanceOf(MeetingLookup::class, $meetings);
        $lessons = self::getContainer()->get(LessonMeetings::class);
        self::assertInstanceOf(LessonMeetings::class, $lessons);

        $slot = match (true) {
            $choice->sessionId !== null => $meetings->slotOfSession($choice->session()),
            $choice->scheduleId !== null => $meetings->slotOfSchedule(
                $choice->schedule(),
                $choice->day(),
                ArchivedStructure::of($archived),
            ),
            default => null,
        };

        return $lessons->chosenLink($stageId, $choice, OfferedMeetings::of($slot), $editing);
    }
}
