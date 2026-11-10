<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\HomeworkTasks;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonLifecycleTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    /**
     * @var ?array{Stage, Schedule}
     */
    private ?array $thirdClass = null;

    private const string NOW = '2026-11-16 10:00:00+01';

    private const string COMPLETED_AT = '2026-11-15 12:00:00+01';

    private const string ARCHIVED_AT = '2026-11-15 13:00:00+01';

    private const string DRAFT = '';

    private const string READY = "status = 'ready'";

    private const string COMPLETED = "status = 'completed', completed_at = '" . self::COMPLETED_AT . "'";

    private const string ARCHIVED = "archived_at = '" . self::ARCHIVED_AT . "'";

    private const string ROW = <<<'SQL'
        SELECT status, completed_at::text AS completed_at, archived_at::text AS archived_at, version::text AS version
        FROM learning.lesson WHERE id = ?
        SQL;

    private const string TASK_ROW = <<<'SQL'
        SELECT status, archived_at::text AS archived_at FROM learning.homework_assignment WHERE id = ?
        SQL;

    /**
     * @param array<string, ?string> $row
     */
    #[DataProvider('statusChanges')]
    public function testEveryStatusMovesToEveryStatus(string $state, string $status, ?string $refusal, array $row): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $lesson = $this->lesson($state);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($lesson, 'status'), ['status' => $status]);

        self::assertResponseStatusCodeSame($refusal === null ? 200 : 409);
        self::assertSame($refusal ?? $row['status'] ?? null, $this->answer());
        self::assertSame($row, $this->stored($lesson));
    }

    /**
     * @return iterable<string, array{string, string, ?string, array<string, ?string>}>
     */
    public static function statusChanges(): iterable
    {
        $draft = self::row('draft', completedAt: null, version: '1');
        $ready = self::row('ready', completedAt: null, version: '1');
        $completed = self::row('completed', self::COMPLETED_AT, version: '1');
        $completedNow = self::row('completed', self::NOW, version: '2');

        yield 'draft to draft' => [self::DRAFT, 'draft', null, $draft];
        yield 'draft to ready' => [self::DRAFT, 'ready', null, self::row('ready', completedAt: null, version: '2')];
        yield 'draft to completed' => [self::DRAFT, 'completed', null, $completedNow];
        yield 'ready to draft' => [self::READY, 'draft', null, self::row('draft', completedAt: null, version: '2')];
        yield 'ready to ready' => [self::READY, 'ready', null, $ready];
        yield 'ready to completed' => [self::READY, 'completed', null, $completedNow];
        yield 'completed to draft' => [
            self::COMPLETED,
            'draft',
            null,
            self::row('draft', completedAt: null, version: '2'),
        ];

        yield 'completed to ready' => [
            self::COMPLETED,
            'ready',
            null,
            self::row('ready', completedAt: null, version: '2'),
        ];

        yield 'completed to completed' => [self::COMPLETED, 'completed', null, $completed];

        yield 'archived draft to ready' => [
            self::ARCHIVED,
            'ready',
            'lessons.archived',
            [...$draft, 'archived_at' => self::ARCHIVED_AT],
        ];

        yield 'archived ready to ready' => [
            self::READY . ', ' . self::ARCHIVED,
            'ready',
            'lessons.archived',
            [...$ready, 'archived_at' => self::ARCHIVED_AT],
        ];

        yield 'archived completed to draft' => [
            self::COMPLETED . ', ' . self::ARCHIVED,
            'draft',
            'lessons.archived',
            [...$completed, 'archived_at' => self::ARCHIVED_AT],
        ];
    }

    public function testALessonIsTaughtOnlyFromItsDayOnUnlessItStaysTaught(): void
    {
        self::mockTime('2026-11-14T10:00:00+01:00');
        $draft = $this->lesson(self::DRAFT);
        $ready = $this->lesson(self::READY, '2026-11-22');
        $completed = $this->lesson(self::COMPLETED, '2026-11-29');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($draft, 'status'), ['status' => 'completed']);
        $this->assertProblem(409, 'lessons.taughtAfterDate');
        $this->jsonRequest('POST', $this->path($ready, 'status'), ['status' => 'completed']);
        $this->assertProblem(409, 'lessons.taughtAfterDate');

        $this->jsonRequest('POST', $this->path($completed, 'status'), ['status' => 'completed']);
        self::assertSame(self::row('completed', self::COMPLETED_AT, version: '1'), $this->stored($completed));
        $this->jsonRequest('POST', $this->path($completed, 'status'), ['status' => 'ready']);
        self::assertSame(self::row('ready', completedAt: null, version: '2'), $this->stored($completed));
    }

    public function testArchivingStampsOnceAndTakesTheOpenTasksAlong(): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $lesson = $this->lesson(self::COMPLETED);
        $tasks = new HomeworkTasks(self::getContainer());
        $open = $tasks->draft($lesson, dueDate: null);
        $archived = $tasks->draft($lesson, dueDate: null);

        $this->update(
            'learning.homework_assignment',
            $archived->id()->toRfc4122(),
            "status = 'archived', " . self::ARCHIVED,
        );

        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($lesson, 'archive'));
        $this->assertJsonPath('homeworkArchived', 1);

        self::assertSame(
            [...self::row('completed', self::COMPLETED_AT, version: '2'), 'archived_at' => self::NOW],
            $this->stored($lesson),
        );

        self::assertSame(['status' => 'archived', 'archived_at' => self::NOW], $this->task($open));
        self::assertSame(['status' => 'archived', 'archived_at' => self::ARCHIVED_AT], $this->task($archived));

        self::mockTime('2026-11-17T10:00:00+01:00');
        $this->jsonRequest('POST', $this->path($lesson, 'archive'));
        $this->assertJsonPath('homeworkArchived', 0);

        self::assertSame(
            [...self::row('completed', self::COMPLETED_AT, version: '2'), 'archived_at' => self::NOW],
            $this->stored($lesson),
        );
    }

    public function testRestoringNeedsTheMeetingFreeLeavesTheTasksArchivedAndAlwaysAnswers(): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $lesson = $this->lesson(self::ARCHIVED);
        $task = new HomeworkTasks(self::getContainer())->draft($lesson, dueDate: null);

        $this->update(
            'learning.homework_assignment',
            $task->id()->toRfc4122(),
            "status = 'archived', " . self::ARCHIVED,
        );

        $unarchived = $this->lesson(self::READY, '2026-11-22');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($lesson, 'restore'));
        self::assertResponseIsSuccessful();
        self::assertSame(self::row('draft', completedAt: null, version: '2'), $this->stored($lesson));
        self::assertSame(['status' => 'archived', 'archived_at' => self::ARCHIVED_AT], $this->task($task));

        $this->jsonRequest('POST', $this->path($unarchived, 'restore'));
        self::assertResponseIsSuccessful();
        self::assertSame(self::row('ready', completedAt: null, version: '1'), $this->stored($unarchived));
    }

    public function testALessonIsNotRestoredOverTheLessonThatNowHoldsItsMeeting(): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $archived = $this->lesson(self::ARCHIVED);
        $this->lesson(self::DRAFT, title: 'The Prodigal Son');
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->jsonRequest('POST', $this->path($archived, 'restore'));
        $this->assertProblem(409, 'lessons.meetingHasLesson', ['title' => 'The Prodigal Son']);

        self::assertSame(
            [...self::row('draft', completedAt: null, version: '1'), 'archived_at' => self::ARCHIVED_AT],
            $this->stored($archived),
        );
    }

    /**
     * @return array<string, ?string>
     */
    private static function row(string $status, ?string $completedAt, string $version): array
    {
        return ['status' => $status, 'completed_at' => $completedAt, 'archived_at' => null, 'version' => $version];
    }

    private function lesson(string $state, string $date = '2026-11-15', string $title = 'The Good Samaritan'): Lesson
    {
        $this->thirdClass ??= $this->thirdClass();
        [$stage, $schedule] = $this->thirdClass;
        $lesson = new Lessons(self::getContainer())->stored($stage, $schedule, $date, $title);

        if ($state !== self::DRAFT) {
            $this->update('learning.lesson', $lesson->id()->toRfc4122(), $state);
        }

        return $lesson;
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

    private function update(string $table, string $id, string $set): void
    {
        new Database(self::getContainer())
            ->connection()
            ->executeStatement('UPDATE ' . $table . ' SET ' . $set . ' WHERE id = ?', [$id]);
    }

    private function path(Lesson $lesson, string $action): string
    {
        return '/api/lessons/' . $lesson->id()->toRfc4122() . '/' . $action;
    }

    private function answer(): ?string
    {
        $body = $this->responseJson();

        return Type::of($body['code'] ?? $body['status'] ?? null)->toStringOrNull();
    }

    /**
     * @return array<string, ?string>
     */
    private function stored(Lesson $lesson): array
    {
        return $this->rowOf(self::ROW, $lesson->id()->toRfc4122());
    }

    /**
     * @return array<string, ?string>
     */
    private function task(HomeworkAssignment $task): array
    {
        return $this->rowOf(self::TASK_ROW, $task->id()->toRfc4122());
    }

    /**
     * @return array<string, ?string>
     */
    private function rowOf(string $sql, string $id): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative($sql, [$id]);

        return array_map(
            static fn (mixed $value): ?string => Type::of($value)->toStringOrNull(),
            $row === false ? [] : $row,
        );
    }
}
