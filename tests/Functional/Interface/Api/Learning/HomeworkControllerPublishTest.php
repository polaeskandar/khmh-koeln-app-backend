<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkControllerPublishTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testAPublishStampsTheTimeOnceAndARetryChangesNothing(): void
    {
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass());
        $this->signInWithRoles('daniel@example.org', 'servant');

        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->publish($task);
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('status', 'published');
        $this->assertJsonPath('publishedAt', '2026-11-08T10:00:00+01:00');

        self::mockTime('2026-11-08T11:00:00+01:00');
        $this->publish($task);
        $this->assertJsonPath('publishedAt', '2026-11-08T10:00:00+01:00');
    }

    public function testADraftThatWasPublishedBeforeGetsANewTime(): void
    {
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass());

        new Database(self::getContainer())
            ->connection()
            ->update(
                'learning.homework_assignment',
                ['published_at' => '2026-11-01 10:00:00+01'],
                ['id' => $task->id()->toRfc4122()],
            );

        $this->signInWithRoles('daniel@example.org', 'servant');
        self::mockTime('2026-11-08T10:00:00+01:00');
        $this->publish($task);

        $this->assertJsonPath('publishedAt', '2026-11-08T10:00:00+01:00');
    }

    public function testArchivedTasksLessonsAndStagesRefuse(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $task = $this->task($lesson);
        $connection = new Database(self::getContainer())->connection();
        $archived = ['archived_at' => '2026-11-01 12:00:00+01'];
        $this->signInWithRoles('daniel@example.org', 'servant');

        $connection->update('organization.stage', $archived, ['id' => $lesson->stageId()->toRfc4122()]);
        $this->publish($task);
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);

        $connection->update('organization.stage', ['archived_at' => null], ['id' => $lesson->stageId()->toRfc4122()]);
        $connection->update('learning.lesson', $archived, ['id' => $lesson->id()->toRfc4122()]);
        $this->publish($task);
        $this->assertProblem(409, 'homework.lessonArchived');

        $connection->update(
            'learning.homework_assignment',
            ['status' => 'archived', ...$archived],
            ['id' => $task->id()->toRfc4122()],
        );

        $this->publish($task);
        $this->assertProblem(409, 'homework.archivedNoPublish');
    }

    public function testUnknownTasksAndAccess(): void
    {
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass());

        $this->publish($task);
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->publish($task);
        $this->assertProblem(403, 'homework.changeStageOnly');

        $this->signInWithRoles('daniel@example.org', 'servant');

        foreach ([Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $unknown) {
            $this->jsonRequest('POST', '/api/homework/' . $unknown . '/publish', []);
            $this->assertProblem(404, 'notFound.homework');
        }
    }

    private function publish(HomeworkAssignment $task): void
    {
        $this->jsonRequest('POST', '/api/homework/' . $task->id()->toRfc4122() . '/publish', []);
    }

    private function task(Lesson $lesson): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        new Database(self::getContainer())->persisted($task);

        return $task;
    }
}
