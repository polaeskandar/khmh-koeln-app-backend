<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use App\Tests\Support\JsonBody;
use App\Tests\Support\Logs;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerListTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testEachTabHasItsOrderAndTheSearchKeepsTheCounts(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$third, $class] = $this->thirdClass();
        new Schedules(self::getContainer())->session($class, '2026-11-08', "status = 'active', opened_at = created_at");
        $lessons = new Lessons(self::getContainer());
        $lessons->stored($third, $class, '2026-11-22', 'The Lost Sheep');
        $samaritan = $lessons->stored($third, $class, '2026-11-15', passage: 'Luke 10:25-37');
        $lessons->stored($third, $class, '2026-11-08', 'Creation');
        $lessons->stored($third, $class, '2026-10-25', 'Noah');
        $lessons->stored($third, $class, '2026-11-01', 'Abraham');
        $this->archive($lessons->stored($third, $class, '2026-10-18', 'Moses'));
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($third));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('tabs', ['upcoming' => 3, 'previous' => 2, 'archived' => 1]);
        self::assertSame(['Creation', 'The Good Samaritan', 'The Lost Sheep'], $this->titles());
        $this->assertJsonPath('items.0.meeting.kind', 'session');
        $this->assertJsonPath('items.0.meeting.status', 'active');
        $this->assertJsonPath('items.1.meeting.kind', 'planned');
        $this->assertJsonPath('stage.name', '3rd Class');
        $this->assertJsonPath('service.name', 'Sunday School');
        $this->assertJsonPath('archived', false);
        self::assertArrayNotHasKey('can', $this->responseJson());

        $this->jsonRequest('GET', $this->path($third) . '?tab=previous');
        self::assertSame(['Abraham', 'Noah'], $this->titles());

        $this->jsonRequest('GET', $this->path($third) . '?tab=archived');
        self::assertSame(['Moses'], $this->titles());

        $this->jsonRequest('GET', $this->path($third) . '?q=LUKE');
        self::assertSame(['The Good Samaritan'], $this->titles());
        $this->assertJsonPath('items.0.id', $samaritan->id()->toRfc4122());
        $this->assertJsonPath('tabs', ['upcoming' => 3, 'previous' => 2, 'archived' => 1]);

        $this->jsonRequest('GET', $this->path($third) . '?tab=deleted');
        $this->assertFieldError('tab', 'validation.invalidChoice', ['choices' => '"upcoming", "previous", "archived"']);
    }

    public function testHomeworkCountsLeaveArchivedTasksOutAndAGoneResponsibleIsNull(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$third, $class] = $this->thirdClass();
        $lesson = new Lessons(self::getContainer())->stored($third, $class, '2026-11-15', passage: 'Luke 10:25-37');
        $this->task($lesson, '');
        $this->task($lesson, "status = 'published', published_at = created_at");
        $this->task($lesson, "status = 'archived', archived_at = created_at");
        $gone = Uuid::v7()->toRfc4122();

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['responsible_id' => $gone], ['id' => $lesson->id()->toRfc4122()]);

        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', $this->path($third));

        $this->assertJsonPath('items.0.homework', ['total' => 2, 'published' => 1, 'drafts' => 1]);
        $this->assertJsonPath('items.0.responsible', null);
        $this->assertJsonPath('items.0.passage', 'Luke 10:25-37');
    }

    public function testArchivedStagesCannotTakeNewLessonsAndParentsAreRefused(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$third] = $this->thirdClass();

        new Database(self::getContainer())
            ->connection()
            ->update(
                'organization.stage',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $third->id()->toRfc4122()],
            );

        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', $this->path($third));
        $this->assertJsonPath('archived', true);

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $stageId) {
            $this->jsonRequest('GET', '/api/stages/' . $stageId . '/lessons');
            $this->assertProblem(404, 'notFound.stage');
        }

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('GET', $this->path($third));
        $this->assertProblem(403, 'forbidden');
    }

    public function testTwentyFiveLessonsCostAsManyStatementsAsOne(): void
    {
        self::mockTime('2026-11-08T12:08:00+01:00');
        [$third, $class] = $this->thirdClass();
        $lessons = new Lessons(self::getContainer());
        $this->task($lessons->stored($third, $class, '2026-11-08'), '');
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($third));
        $one = count(new Logs(self::getContainer())->containing('SELECT'));

        foreach (range(start: 1, end: 24) as $week) {
            $lesson = $lessons->stored($third, $class, LocalDate::of('2026-11-08')->addDays($week * 7)->toString());

            $this->task($lesson, '');
        }

        $this->jsonRequest('GET', $this->path($third));

        self::assertCount(25, $this->titles());
        self::assertCount($one, new Logs(self::getContainer())->containing('SELECT'));
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

    private function task(Lesson $lesson, string $set): void
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
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
    }

    private function archive(Lesson $lesson): void
    {
        new Database(self::getContainer())
            ->connection()
            ->update(
                'learning.lesson',
                ['archived_at' => '2026-11-01 12:00:00+01'],
                ['id' => $lesson->id()->toRfc4122()],
            );
    }

    private function path(Stage $stage): string
    {
        return '/api/stages/' . $stage->id()->toRfc4122() . '/lessons';
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
