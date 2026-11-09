<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkReportRepositoryInterface;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\ParentChildren;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianLinkRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use App\Workflow\Learning\Model\VisibleHomework;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class VisibleHomeworkTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string TODAY = '2026-11-08';

    private ?Service $service = null;

    public function testOnlyPublishedHomeworkOfTheChildsStageCounts(): void
    {
        $third = $this->lesson();
        $first = $this->lesson('1st Class');
        $families = new Families(self::getContainer());
        $parent = $families->account('hoda@example.org');
        $mina = $this->child('Mina', $third, '2026-09-01', null);
        $mimi = $this->child('Mimi', $first, '2026-09-01', null);
        $families->link($parent, $mina);
        $families->link($parent, $mimi);
        $this->task($third, 'published');
        $this->task($third, 'draft');
        $this->task($third, 'archived');

        $counts = $this->counts($parent, self::TODAY);

        self::assertSame(['Mimi' => [0, 0], 'Mina' => [0, 1]], $counts);
    }

    public function testJoiningLeavingAndDeactivatedChildren(): void
    {
        $third = $this->lesson();
        $families = new Families(self::getContainer());
        $parent = $families->account('hoda@example.org');
        $joined = $this->child('Joined', $third, '2026-11-08', null);
        $left = $this->child('Left', $third, '2026-09-01', '2026-10-31');
        $inactive = $this->child('Inactive', $third, '2026-09-01', null);

        foreach ([$joined, $left, $inactive] as $child) {
            $families->link($parent, $child);
        }

        $reported = $this->task($third, 'published');
        $this->task($third, 'published');
        new Reports(self::getContainer())->stored($reported, $left);
        new Reports(self::getContainer())->stored($reported, $inactive);

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $inactive->id()->toRfc4122()]);

        new Database(self::getContainer())->entityManager()->clear();

        self::assertSame(
            ['Inactive' => [1, 0], 'Joined' => [0, 2], 'Left' => [1, 0]],
            $this->counts($parent, self::TODAY),
        );
    }

    public function testAMembershipEndingTodayCountsUntilMidnightInBerlin(): void
    {
        $third = $this->lesson();
        $families = new Families(self::getContainer());
        $parent = $families->account('hoda@example.org');
        $families->link($parent, $this->child('Ending', $third, '2026-09-01', '2026-11-08'));
        $this->task($third, 'published');

        self::assertSame(['Ending' => [0, 1]], $this->counts($parent, '2026-11-08'));
        self::assertSame(['Ending' => [0, 0]], $this->counts($parent, '2026-11-09'));
    }

    public function testOtherFamiliesRevokedLinksDisabledViewersAndUndoneReports(): void
    {
        $third = $this->lesson();
        $families = new Families(self::getContainer());
        $parent = $families->account('hoda@example.org');
        $other = $families->account('other@example.org');
        $disabled = $families->account('gone@example.org', ['disabled_at' => '2026-11-01 12:00:00+01']);
        $mina = $this->child('Mina', $third, '2026-09-01', null);
        $theirs = $this->child('Theirs', $third, '2026-09-01', null);
        $families->link($parent, $mina);
        $families->link($other, $theirs);
        $families->link($parent, $theirs, 'revoked');
        $families->link($disabled, $mina);
        $task = $this->task($third, 'published');
        new Reports(self::getContainer())->stored($task, $mina, undoneAt: '2026-11-07T10:05:00+01:00');

        self::assertSame(['Mina' => [0, 1]], $this->counts($parent, self::TODAY));
        self::assertSame([], $this->counts($disabled, self::TODAY));

        $visible = $this->visibleTo($parent, self::TODAY);

        self::assertNull($visible->of($theirs->id()->toRfc4122()));
        self::assertNull($visible->of('not-a-uuid'));
        self::assertSame($task, $visible->requireTask($mina->id()->toRfc4122(), $task->id()->toRfc4122())->task);

        $this->expectExceptionObject(NotFound::coded('homework.notAvailable'));
        $visible->requireTask($mina->id()->toRfc4122(), Uuid::v7()->toRfc4122());
    }

    public function testMoreChildrenAndTasksCostNoMoreStatements(): void
    {
        $third = $this->lesson();
        $families = new Families(self::getContainer());
        $parent = $families->account('hoda@example.org');
        $families->link($parent, $this->child('First', $third, '2026-09-01', null));
        $this->task($third, 'published');
        $logs = new Logs(self::getContainer());
        $this->counts($parent, self::TODAY);
        $start = count($logs->containing('SELECT'));
        $this->counts($parent, self::TODAY);
        $few = count($logs->containing('SELECT')) - $start;

        foreach (range(start: 1, end: 4) as $number) {
            $families->link($parent, $this->child('Child ' . $number, $third, '2026-09-01', null));
        }

        array_map(fn (): HomeworkTask => $this->task($third, 'published'), range(start: 1, end: 19));

        $start = count($logs->containing('SELECT'));
        $counts = $this->counts($parent, self::TODAY);

        self::assertCount(5, $counts);
        self::assertCount($start + $few, $logs->containing('SELECT'));
    }

    /**
     * @return array<string, array{int, int}>
     */
    private function counts(string $viewer, string $today): array
    {
        $counts = [];

        foreach ($this->visibleTo($viewer, $today)->children as $child) {
            $counts[$child->child->name()->firstName] = [$child->tasks->countDone(), $child->tasks->countToDo()];
        }

        ksort($counts);

        return $counts;
    }

    private function child(string $name, Lesson $lesson, string $startsOn, ?string $endsOn): Person
    {
        $stage = new Database(self::getContainer())
            ->entityManager()
            ->find(Stage::class, $lesson->stageId());

        self::assertInstanceOf(Stage::class, $stage);
        $rosters = new Rosters(self::getContainer());
        $child = $rosters->person($name);
        $rosters->place($child, $stage->service(), $stage, $startsOn, $endsOn);

        return $child;
    }

    private function task(Lesson $lesson, string $status): HomeworkTask
    {
        $task = new HomeworkTask(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            createdBy: null,
        );

        if ($status === 'published') {
            $task->changeState($task->state()->published(new DateTimeImmutable('2026-11-01T09:00:00+01:00')));
        }

        $database = new Database(self::getContainer());
        $database->persisted($task);

        if ($status === 'archived') {
            $database->connection()->executeStatement("UPDATE learning.homework_assignment SET status = 'archived', archived_at = now() WHERE id = ?", [$task->id()->toRfc4122()]);
        }

        return $task;
    }

    private function lesson(string $stageName = '3rd Class'): Lesson
    {
        $this->service ??= Structure::service();
        $stage = Structure::stage($this->service, $stageName);
        new Database(self::getContainer())->persisted($this->service, $stage);
        $class = new Schedules(self::getContainer())->weekly($this->service, 'Class ' . $stageName, 7, stage: $stage);

        return new Lessons(self::getContainer())->stored($stage, $class, '2026-11-15');
    }

    private function visibleTo(string $viewer, string $today): VisibleHomework
    {
        self::mockTime($today . 'T12:00:00+01:00');
        $container = self::getContainer();
        $accounts = $container->get(AccountLookup::class);
        $calendar = $container->get(Calendar::class);
        $links = $container->get(GuardianLinkRepositoryInterface::class);
        $people = $container->get(PersonRepositoryInterface::class);
        $homework = $container->get(HomeworkRepositoryInterface::class);
        $reports = $container->get(HomeworkReportRepositoryInterface::class);
        self::assertInstanceOf(AccountLookup::class, $accounts);
        self::assertInstanceOf(Calendar::class, $calendar);
        self::assertInstanceOf(GuardianLinkRepositoryInterface::class, $links);
        self::assertInstanceOf(PersonRepositoryInterface::class, $people);
        self::assertInstanceOf(HomeworkRepositoryInterface::class, $homework);
        self::assertInstanceOf(HomeworkReportRepositoryInterface::class, $reports);

        $viewerId = Uuid::fromString($viewer);
        $family = new ParentChildren($links, $people, $calendar)->of($viewerId);

        if ($accounts->find($viewerId)?->status()->isActive() !== true) {
            return VisibleHomework::none($family);
        }

        $tasks = new PupilHomework($homework, $reports)->of(VisibleHomework::pupilsOf($family));

        return VisibleHomework::forFamily($family, $tasks);
    }
}
