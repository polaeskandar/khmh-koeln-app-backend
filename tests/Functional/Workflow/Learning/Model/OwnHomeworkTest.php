<?php

declare(strict_types=1);

namespace App\Tests\Functional\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Application\Service\HomeworkReports;
use App\Context\Learning\Application\Service\PupilHomework;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberRecords;
use App\Context\People\Application\Service\ParentChildren;
use App\Context\People\Application\Service\StageRosters;
use App\Context\People\Domain\Entity\Person;
use App\Tests\Support\Context\Learning\Domain\Entity\CopticCourse;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use App\Tests\Support\Logs;
use App\Workflow\Learning\Model\AudienceAccounts;
use App\Workflow\Learning\Model\AudienceKeys;
use App\Workflow\Learning\Model\AudienceRow;
use App\Workflow\Learning\Model\OwnHomework;
use App\Workflow\Learning\Model\TaskAudiences;
use App\Workflow\Learning\Model\VisibleHomework;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class OwnHomeworkTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    #[Override]
    protected function setUp(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
    }

    public function testAMemberSeesTheLevelsPublishedTasksAndTheirOwnReports(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $course->report('greeting', $nevine, 'nevine@example.org');

        self::assertSame(
            ['Write the first eight letters' => false, 'Say the Easter greeting at home' => true],
            $this->titles($nevine),
        );
    }

    public function testJoinersSeeTheLevelLeaversOnlyWhatWasReportedAndAnInactiveRecordNothing(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $joiner = $course->member('joiner@example.org', 'Joiner', since: '2026-11-05');
        $leaver = $course->member('leaver@example.org', 'Leaver', until: '2026-11-01');
        $course->report('greeting', $leaver, 'leaver@example.org');
        $inactive = $course->member('inactive@example.org', 'Inactive');

        new Database(self::getContainer())
            ->connection()
            ->update('people.person', ['status' => 'inactive'], ['id' => $inactive->id()->toRfc4122()]);

        new Database(self::getContainer())->entityManager()->clear();

        self::assertSame(
            ['Write the first eight letters', 'Say the Easter greeting at home'],
            array_keys($this->titles($joiner)),
        );

        self::assertSame(['Say the Easter greeting at home' => true], $this->titles($leaver));
        self::assertNull($this->own($inactive)->homework);
    }

    public function testVisibilityEqualsTheAudienceOfEveryPublishedTask(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $leaver = $course->member('leaver@example.org', 'Leaver', until: '2026-11-01');
        $course->report('greeting', $leaver, 'leaver@example.org');

        foreach (['greeting', 'letters', 'samaritan'] as $name) {
            $task = $course->task($name);
            $audience = $this->audienceOf($task);

            foreach ([$nevine, $leaver] as $member) {
                self::assertSame(
                    \in_array($member->id()->toRfc4122(), $audience, strict: true),
                    $this->own($member)->task($task->id()) !== null,
                    $member->name()->firstName . ' ' . $name,
                );
            }
        }
    }

    public function testMoreTasksCostNoMoreStatements(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $nevine = $course->member('nevine@example.org', 'Nevine');
        $logs = new Logs(self::getContainer());
        $this->own($nevine);
        $start = \count($logs->containing('SELECT'));
        $this->own($nevine);
        $one = \count($logs->containing('SELECT')) - $start;

        $course->publishMore(19);

        $start = \count($logs->containing('SELECT'));
        $this->own($nevine);

        self::assertCount($start + $one, $logs->containing('SELECT'));
    }

    public function testAnOlderChildAndTheirParentSeeTheSameTasksAndReport(): void
    {
        $course = CopticCourse::seeded(self::getContainer());
        $youssef = $course->member('youssef@example.org', 'Youssef');
        $families = new Families(self::getContainer());
        $hoda = $families->account('hoda@example.org');
        $families->link($hoda, $youssef);
        $course->report('greeting', $youssef, 'hoda@example.org');

        $family = self::service(ParentChildren::class)->getFamilyOf(Uuid::fromString($hoda));

        $asParent = VisibleHomework::forFamily(
            $family,
            self::service(PupilHomework::class)->listTasksOf(VisibleHomework::pupilsOf($family)),
        )->of($youssef->id());

        $own = $this->own($youssef)->homework;

        $summary = static fn (VisibleTask $task): string => (
            $task->task->id()->toRfc4122() . ' ' . ($task->report?->id()->toRfc4122() ?? '-')
        );

        self::assertNotNull($asParent);
        self::assertNotNull($own);
        self::assertSame(array_map($summary, $asParent->tasks->tasks), array_map($summary, $own->tasks->tasks));
    }

    /**
     * @return list<string>
     */
    private function audienceOf(HomeworkAssignment $task): array
    {
        $reports = self::service(HomeworkReports::class)->getReading($task->id())->reports;
        $stage = self::service(StructureLookup::class)->getStage($task->stageId());
        $key = AudienceKeys::keyOf($stage, $reports->today);
        $book = self::service(StageRosters::class)->getRosterBookOn([$key], $reports->reporterIds());

        $audiences = TaskAudiences::of(
            $reports,
            new ArrayCollection([$task->stageId()->toRfc4122() => $key]),
            $book,
            AudienceAccounts::of(
                $book,
                self::service(AccountLookup::class)->listByIds(TaskAudiences::accountIdsOf($book, $reports)),
                self::service(AccountLookup::class)->listOwnAccountsOf($book->personIds()),
            ),
        );

        return array_map(static fn (AudienceRow $row): string => $row->person->id, $audiences->rowsOf($task));
    }

    /**
     * @return array<string, bool>
     */
    private function titles(Person $member): array
    {
        $tasks = $this->own($member)->homework?->tasks->tasks ?? [];

        return array_combine(
            array_map(static fn (VisibleTask $task): string => $task->task->text()->title, $tasks),
            array_map(static fn (VisibleTask $task): bool => $task->isDone(), $tasks),
        );
    }

    private function own(Person $member): OwnHomework
    {
        $record = self::service(MemberRecords::class)->getOwnRecord($member->id());

        return OwnHomework::of(
            $record,
            self::service(PupilHomework::class)->listTasksOf(OwnHomework::pupilsOf($record)),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(string $class): object
    {
        $service = self::getContainer()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
