<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\HomeworkCompletion;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Person;
use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Organization\Domain\Entity\ServedGroups;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Context\Scheduling\Domain\Entity\Schedules;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkControllerListTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string PUBLISHED = "status = 'published', published_at = '2026-11-01 09:00:00+01'";

    public function testTabsOrderCountsAndTheDefaultLesson(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$stage, $class] = $this->thirdClass();
        $lessons = new Lessons(self::getContainer());
        $first = $lessons->stored($stage, $class, '2026-11-01', 'Creation');
        $today = $lessons->stored($stage, $class, '2026-11-08', 'Noah');
        $later = $lessons->stored($stage, $class, '2026-11-22', 'Abraham');
        $this->task($first, 'Oldest', null, self::PUBLISHED);
        $this->task($first, 'Second', '2026-11-03', self::PUBLISHED);
        $this->task($today, 'Third', null, self::PUBLISHED);
        $reported = $this->task($later, 'Newest', '2026-11-29', self::PUBLISHED);
        $this->task($later, 'Draft', null, '');
        $this->task($today, 'Archived', '2026-11-10', "status = 'archived', archived_at = '2026-11-07 09:00:00+01'");
        $children = $this->roster($stage, 14);
        $families = new Families(self::getContainer());

        foreach (array_slice($children, offset: 0, length: 10) as $index => $child) {
            $families->link($families->account('parent' . $index . '@example.org'), $child);
        }

        new Database(self::getContainer())->persisted(
            new HomeworkCompletion(
                $reported,
                ($children[0] ?? self::fail('No children.'))->id(),
                new DateTimeImmutable('2026-11-07T10:00:00+01:00'),
                completedBy: null,
            ),
        );

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));
        $this->jsonRequest('GET', $this->path($stage));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('tabs', ['published' => 4, 'draft' => 1, 'archived' => 1]);
        self::assertSame(['Newest', 'Draft', 'Archived', 'Third', 'Second', 'Oldest'], $this->titles());
        $this->assertJsonPath('items.1.counts', null);
        $this->assertJsonPath('items.2.counts.assigned', 14);

        $this->assertJsonPath('items.0.counts', [
            'assigned' => 14,
            'completed' => 1,
            'notCompleted' => 13,
            'completedAfterDueDate' => 0,
            'withoutParentAccount' => 4,
            'withoutAccount' => 4,
            'reportedTotal' => 1,
        ]);

        $this->assertJsonPath('defaultLessonId', $today->id()->toRfc4122());
        $this->assertJsonPath('lessons.0.title', 'Abraham');
        $this->assertJsonPath('audienceSize', 14);
        $this->assertJsonPath('archived', false);
        $this->assertJsonPath('items.0.lesson.archived', false);
        self::assertArrayNotHasKey('can', $this->responseJson());
    }

    public function testAnArchivedStageIsMarkedAndTwentyTasksCostAsManyStatementsAsOne(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        [$stage, $class] = $this->thirdClass();
        $lesson = new Lessons(self::getContainer())->stored($stage, $class, '2026-11-15');
        $this->task($lesson, 'First', null, self::PUBLISHED);
        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        $this->jsonRequest('GET', $this->path($stage));
        $one = count(new Logs(self::getContainer())->containing('SELECT'));

        $lesson = new Database(self::getContainer())
            ->entityManager()
            ->find(Lesson::class, $lesson->id());

        self::assertInstanceOf(Lesson::class, $lesson);

        foreach (range(start: 1, end: 19) as $number) {
            $this->task($lesson, 'Task ' . $number, null, self::PUBLISHED);
        }

        $this->jsonRequest('GET', $this->path($stage));
        self::assertCount(20, $this->titles());
        self::assertCount($one, new Logs(self::getContainer())->containing('SELECT'));

        new Database(self::getContainer())
            ->connection()
            ->update(
                'organization.stage',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $stage->id()->toRfc4122()],
            );

        $this->jsonRequest('GET', $this->path($stage));
        $this->assertJsonPath('archived', true);
    }

    public function testParentsAnonymousCallersAndUnknownStages(): void
    {
        [$stage] = $this->thirdClass();

        $this->jsonRequest('GET', $this->path($stage));
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('GET', $this->path($stage));
        $this->assertProblem(403, 'forbidden');

        new ServedGroups(self::getContainer())->everyService($this->signInWithRoles('daniel@example.org', 'servant'));

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('GET', '/api/stages/' . $unknown . '/homework');
            $this->assertProblem(404, 'notFound.stage');
        }
    }

    /**
     * @return list<string>
     */
    private function titles(): array
    {
        $items = JsonBody::asArray($this->responseJson()['items'] ?? null, 'items');

        return array_values(array_map(static fn (mixed $item): string => Type::of(
            JsonBody::asArray($item, 'item')['title'] ?? null,
        )->toString(), $items));
    }

    private function path(Stage $stage): string
    {
        return '/api/stages/' . $stage->id()->toRfc4122() . '/homework';
    }

    /**
     * @return list<Person>
     */
    private function roster(Stage $stage, int $size): array
    {
        $rosters = new Rosters(self::getContainer());

        return array_map(
            static function (int $number) use ($rosters, $stage): Person {
                $child = $rosters->person('Child ' . $number);
                $rosters->place($child, $stage->service(), $stage, '2026-09-01');

                return $child;
            },
            range(start: 1, end: $size),
        );
    }

    private function task(Lesson $lesson, string $title, ?string $dueDate, string $set): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of($title, 'Read it twice.', $dueDate === null ? null : LocalDate::of($dueDate)),
            Materials::none(),
            createdBy: null,
        );

        $database = new Database(self::getContainer());
        $database->persisted($task);

        if ($set !== '') {
            $database->connection()->executeStatement('UPDATE learning.homework_assignment SET '
            . $set
            . ' WHERE id = ?', [$task->id()->toRfc4122()]);
        }

        return $task;
    }

    /**
     * @return array{Stage, Schedule}
     */
    private function thirdClass(): array
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);

        return [$third, new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $third)];
    }
}
