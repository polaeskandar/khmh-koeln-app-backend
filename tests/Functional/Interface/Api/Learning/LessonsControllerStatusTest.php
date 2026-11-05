<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Meetings\Domain\Entity\Schedules;
use App\Tests\Support\Context\Organization\Domain\Entity\Structure;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class LessonsControllerStatusTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testTaughtStartsAtMidnightInBerlinAndEveryStepCanBeUndone(): void
    {
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');

        self::mockTime('2026-11-14T22:55:00Z');
        $this->change($lesson, 'ready');
        $this->assertJsonPath('completedAt', null);
        $this->change($lesson, 'completed');
        $this->assertProblem(409, 'lessons.taughtAfterDate');
        self::assertSame(['ready', null], $this->stored($lesson));

        self::mockTime('2026-11-14T23:05:00Z');
        $this->change($lesson, 'completed');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'completed');
        $this->assertJsonPath('completedAt', '2026-11-15T00:05:00+01:00');
        self::assertSame(['completed', '2026-11-14 23:05:00'], $this->stored($lesson));

        $this->change($lesson, 'ready');
        $this->assertJsonPath('completedAt', null);
        self::assertSame(['ready', null], $this->stored($lesson));

        $this->change($lesson, 'draft');
        $this->change($lesson, 'completed');
        $this->assertJsonPath('status', 'completed');
    }

    public function testTheSameStatusChangesNothing(): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->change($lesson, 'ready');
        $updatedAt = $this->updatedAt($lesson);

        self::mockTime('2026-11-16T11:00:00+01:00');
        $this->change($lesson, 'ready');

        self::assertResponseIsSuccessful();
        self::assertSame($updatedAt, $this->updatedAt($lesson));
    }

    public function testInvalidValuesArchivedLessonsAndStagesUnknownIdsAndAccess(): void
    {
        self::mockTime('2026-11-16T10:00:00+01:00');
        $lesson = $this->lesson();
        $database = new Database(self::getContainer())->connection();
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->change($lesson, 'taught');
        $this->assertFieldError('status', 'validation.invalidChoice', ['choices' => '"draft", "ready", "completed"']);

        $database->executeStatement("UPDATE organization.stage SET archived_at = '2026-11-01 12:00:00+01'");
        $this->change($lesson, 'ready');
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);

        $database->update(
            'learning.lesson',
            ['archived_at' => '2026-11-02 12:00:00+01'],
            ['id' => $lesson->id()->toRfc4122()],
        );

        $this->change($lesson, 'ready');
        $this->assertProblem(409, 'lessons.archived');

        $this->jsonRequest('POST', '/api/lessons/' . Uuid::v7()->toRfc4122() . '/status', ['status' => 'ready']);
        $this->assertProblem(404, 'notFound.lesson');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->change($lesson, 'ready');
        $this->assertProblem(403, 'lessons.changeStageOnly');
    }

    private function change(Lesson $lesson, string $status): void
    {
        $this->jsonRequest('POST', '/api/lessons/' . $lesson->id()->toRfc4122() . '/status', ['status' => $status]);
    }

    /**
     * @return list<mixed>
     */
    private function stored(Lesson $lesson): array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchNumeric("SELECT status, to_char(completed_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') "
            . 'FROM learning.lesson WHERE id = ?', [$lesson->id()->toRfc4122()]);

        return $row === false ? [] : $row;
    }

    private function updatedAt(Lesson $lesson): string
    {
        $updatedAt = new Database(self::getContainer())
            ->connection()
            ->fetchOne('SELECT updated_at::text FROM learning.lesson WHERE id = ?', [$lesson->id()->toRfc4122()]);

        self::assertIsString($updatedAt);

        return $updatedAt;
    }

    private function lesson(): Lesson
    {
        $service = Structure::service();
        $third = Structure::stage($service);
        new Database(self::getContainer())->persisted($service, $third);
        $class = new Schedules(self::getContainer())->weekly($service, 'Sunday class', 7, stage: $third);

        return new Lessons(self::getContainer())->stored($third, $class, '2026-11-15');
    }
}
