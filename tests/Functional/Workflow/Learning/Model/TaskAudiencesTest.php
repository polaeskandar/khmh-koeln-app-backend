<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Context\Learning\Domain\Entity\HomeworkTasks;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use App\Tests\Support\Workflow\Learning\Model\TaskAudienceLookups;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\Learning\Model\AudienceSize;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TaskAudiencesTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testTheRosterOfTodayInBerlinIsTheAudience(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $task = $this->homework()->draft(new Lessons(self::getContainer())->inThirdClass(), null);
        [$service, $stage] = $this->homework()->stageOf($task);
        $rosters = new Rosters(self::getContainer());
        $rosters->place($rosters->person('Today'), $service, $stage, '2026-11-08');
        $rosters->place($rosters->person('Ending'), $service, $stage, '2026-09-01', '2026-11-08');
        $rosters->place($rosters->person('Tomorrow'), $service, $stage, '2026-11-09');
        $rosters->place($rosters->person('Yesterday'), $service, $stage, '2026-09-01', '2026-11-07');
        $inactive = $rosters->person('Inactive');
        $rosters->place($inactive, $service, $stage, '2026-09-01');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $inactive->id()->toRfc4122()]);

        new Database(self::getContainer())->entityManager()->clear();

        self::assertSame(
            ['Ending', 'Today'],
            $this->names(new TaskAudienceLookups(self::getContainer())->audiences([$task])->rowsOf($task)),
        );

        $key = AudienceKeys::keyOf($stage, LocalDate::of('2026-11-08'));

        self::assertSame(2, AudienceSize::of(
            new TaskAudienceLookups(self::getContainer())
                ->rosters()
                ->getRosterBookOn([$key], alsoPersonIds: []),
            $key,
        ));

        self::mockTime('2026-11-08T23:30:00Z');

        self::assertSame(
            ['Today', 'Tomorrow'],
            $this->names(new TaskAudienceLookups(self::getContainer())->audiences([$task])->rowsOf($task)),
        );
    }

    public function testLeaversCountOnlyWithAReportAndAnUndoneReportIsNotCompleted(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $task = $this->homework()->draft(new Lessons(self::getContainer())->inThirdClass(), null);
        [$service, $stage] = $this->homework()->stageOf($task);
        $rosters = new Rosters(self::getContainer());
        $staying = $rosters->person('Staying');
        $undone = $rosters->person('Undone');
        $reportedLeaver = $rosters->person('Reported');
        $silentLeaver = $rosters->person('Silent');

        foreach ([$staying, $undone] as $member) {
            $rosters->place($member, $service, $stage, '2026-09-01');
        }

        foreach ([$reportedLeaver, $silentLeaver] as $leaver) {
            $rosters->place($leaver, $service, $stage, '2026-09-01', '2026-10-31');
        }

        $this->homework()->report($task, $staying, '2026-11-07T10:00:00+01:00');
        $this->homework()->report($task, $reportedLeaver, '2026-10-30T10:00:00+01:00');
        $this->homework()->report($task, $undone, '2026-11-07T11:00:00+01:00', undone: '2026-11-07T11:05:00+01:00');

        $rows = new TaskAudienceLookups(self::getContainer())->audiences([$task])->rowsOf($task);
        self::assertSame(['Staying', 'Undone', 'Reported'], $this->names($rows));
        self::assertSame([true, true, false], array_map(static fn (AudienceRow $row): bool => $row->inStage, $rows));

        self::assertSame(
            [false, true, false],
            array_map(static fn (AudienceRow $row): bool => $row->report === null, $rows),
        );

        $counts = new TaskAudienceLookups(self::getContainer())->audiences([$task])->countsOf($task);

        self::assertSame([2, 1, 1, 0, 1, 1, 2], [
            $counts->assigned,
            $counts->completed,
            $counts->notCompleted(),
            $counts->completedAfterDueDate,
            $counts->open->withoutParentAccount,
            $counts->open->withoutAccount,
            $counts->reportedTotal,
        ]);
    }

    public function testALeaverWhoReportedKeepsTheirParentAccounts(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $task = $this->homework()->draft(new Lessons(self::getContainer())->inThirdClass(), null);
        [$service, $stage] = $this->homework()->stageOf($task);
        $rosters = new Rosters(self::getContainer());
        $families = new Families(self::getContainer());
        $leaver = $rosters->person('Reported');
        $rosters->place($leaver, $service, $stage, '2026-09-01', '2026-10-31');
        $families->link($families->account('hoda@example.org'), $leaver);
        $this->homework()->report($task, $leaver, '2026-10-30T10:00:00+01:00');

        $rows = new TaskAudienceLookups(self::getContainer())->audiences([$task])->rowsOf($task);

        self::assertSame(['Reported'], $this->names($rows));

        self::assertSame(
            [[false, 1]],
            array_map(static fn (AudienceRow $row): array => [$row->inStage, count($row->parentAccounts)], $rows),
        );
    }

    public function testAReportIsLateAfterTheDueDateInBerlin(): void
    {
        self::mockTime('2026-11-23T12:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $late = $this->homework()->draft($lesson, '2026-11-22');
        $onTime = $this->homework()->draft($lesson, '2026-11-22');
        $undated = $this->homework()->draft($lesson, null);
        $child = new Rosters(self::getContainer())->person('Mina');
        [$service, $stage] = $this->homework()->stageOf($late);
        new Rosters(self::getContainer())->place($child, $service, $stage, '2026-09-01');

        $this->homework()->report($late, $child, '2026-11-22T23:30:00Z');
        $this->homework()->report($onTime, $child, '2026-11-22T23:30:00+01:00');
        $this->homework()->report($undated, $child, '2027-01-01T10:00:00+01:00');

        $audiences = new TaskAudienceLookups(self::getContainer())->audiences([$late, $onTime, $undated]);

        self::assertSame(1, $audiences->countsOf($late)->completedAfterDueDate);
        self::assertSame(0, $audiences->countsOf($onTime)->completedAfterDueDate);
        self::assertSame(0, $audiences->countsOf($undated)->completedAfterDueDate);

        self::assertTrue(
            new TaskAudienceLookups(self::getContainer())->audiences([$late])->rowsOf(
                $late,
            )[0]->report->completedAfterDueDate ?? false,
        );
    }

    public function testOnlyApprovedLinksToActiveAccountsAreParentAccounts(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $task = $this->homework()->draft(new Lessons(self::getContainer())->inThirdClass(), null);
        [$service, $stage] = $this->homework()->stageOf($task);
        $rosters = new Rosters(self::getContainer());
        $families = new Families(self::getContainer());
        $linked = $rosters->person('Linked');
        $revoked = $rosters->person('Revoked');
        $disabled = $rosters->person('Disabled');
        $reported = $rosters->person('Reported');

        foreach ([$linked, $revoked, $disabled, $reported] as $child) {
            $rosters->place($child, $service, $stage, '2026-09-01');
        }

        $families->link($families->account('hoda@example.org'), $linked);
        $families->link($families->account('nadia@example.org'), $revoked, 'revoked');
        $families->link($families->account('gone@example.org', ['disabled_at' => '2026-11-01 12:00:00+01']), $disabled);
        $this->homework()->report($task, $reported, '2026-11-07T10:00:00+01:00');

        $rows = new TaskAudienceLookups(self::getContainer())->audiences([$task])->rowsOf($task);
        self::assertSame(['Disabled', 'Linked', 'Reported', 'Revoked'], $this->names($rows));

        self::assertSame(
            [0, 1, 0, 0],
            array_map(static fn (AudienceRow $row): int => count($row->parentAccounts), $rows),
        );

        self::assertSame(
            2,
            new TaskAudienceLookups(self::getContainer())
                ->audiences([$task])
                ->countsOf($task)
                ->open
                ->withoutParentAccount,
        );
    }

    public function testTwentyTasksCostAsManyStatementsAsOne(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $first = $this->homework()->draft($lesson, null);
        [$service, $stage] = $this->homework()->stageOf($first);
        $rosters = new Rosters(self::getContainer());
        $child = $rosters->person('Mina');
        $rosters->place($child, $service, $stage, '2026-09-01');
        $this->homework()->report($first, $child, '2026-11-07T10:00:00+01:00');
        $logs = new Logs(self::getContainer());

        $start = count($logs->containing('SELECT'));
        new TaskAudienceLookups(self::getContainer())->audiences([$first])->countsOf($first);
        $one = count($logs->containing('SELECT')) - $start;

        $tasks = array_map(
            fn (): HomeworkAssignment => $this->homework()->draft($lesson, null),
            range(start: 1, end: 20),
        );

        $before = count($logs->containing('SELECT'));

        new TaskAudienceLookups(self::getContainer())
            ->audiences($tasks)
            ->countsOf($first);

        self::assertCount($before + $one, $logs->containing('SELECT'));
    }

    /**
     * @param list<AudienceRow> $rows
     *
     * @return list<string>
     */
    private function names(array $rows): array
    {
        return array_map(static fn (AudienceRow $row): string => $row->person->firstName, $rows);
    }

    private function homework(): HomeworkTasks
    {
        return new HomeworkTasks(self::getContainer());
    }
}
