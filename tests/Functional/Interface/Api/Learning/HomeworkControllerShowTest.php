<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\People\Domain\Entity\Rosters;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkControllerShowTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testADraftCarriesTheLessonTextsButNoServantNotes(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->roster($lesson, 14);
        $task = $this->task($lesson, null);
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($task));

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('instructions', 'Read it twice.');
        $this->assertJsonPath('resources', [['label' => 'Psalm 23', 'url' => 'https://example.org/psalm']]);
        $this->assertJsonPath('counts', null);
        $this->assertJsonPath('audienceSize', 14);
        $this->assertJsonPath('minDueDate', '2026-11-15');

        $this->assertJsonPath('can', [
            'edit' => true,
            'publish' => true,
            'unpublish' => false,
            'archive' => true,
            'restore' => false,
            'delete' => true,
            'viewProgress' => true,
        ]);

        $this->assertJsonPath('lesson', [
            'id' => $lesson->id()->toRfc4122(),
            'title' => 'The Good Samaritan',
            'teachingDate' => '2026-11-15',
            'passage' => 'Luke 10:25-37',
            'memoryVerse' => 'Love your neighbour.',
        ]);

        self::assertStringNotContainsString('Mina was sad', (string) $this->browser()->getResponse()->getContent());
    }

    public function testTheMinimumDueDateKeepsAnEarlierSavedDate(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $task = $this->task($lesson, '2026-11-15');

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['teaching_date' => '2026-11-22'], ['id' => $lesson->id()->toRfc4122()]);

        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', $this->path($task));

        $this->assertJsonPath('minDueDate', '2026-11-15');
        $this->assertJsonPath('dueDate', '2026-11-15');
    }

    public function testPublishedAndArchivedTasksCarryCountsAndTheirFlags(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $lesson = $this->lesson();
        $this->roster($lesson, 3);
        $published = $this->task($lesson, null);
        $archived = $this->task($lesson, null);
        $connection = new Database(self::getContainer())->connection();

        $connection->executeStatement("UPDATE learning.homework_assignment SET status = 'published', published_at = '2026-11-08 09:00:00+01' WHERE id = ?", [$published->id()->toRfc4122()]);

        $connection->executeStatement("UPDATE learning.homework_assignment SET status = 'archived', archived_at = '2026-11-08 09:30:00+01' WHERE id = ?", [$archived->id()->toRfc4122()]);

        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->jsonRequest('GET', $this->path($published));
        $this->assertJsonPath('counts.assigned', 3);
        $this->assertJsonPath('publishedAt', '2026-11-08T09:00:00+01:00');

        $this->assertJsonPath('can', [
            'edit' => true,
            'publish' => false,
            'unpublish' => true,
            'archive' => true,
            'restore' => false,
            'delete' => false,
            'viewProgress' => true,
        ]);

        $this->jsonRequest('GET', $this->path($archived));
        $this->assertJsonPath('counts.notCompleted', 3);

        $this->assertJsonPath('can', [
            'edit' => false,
            'publish' => false,
            'unpublish' => false,
            'archive' => false,
            'restore' => true,
            'delete' => true,
            'viewProgress' => true,
        ]);

        $connection->update(
            'learning.lesson',
            ['archived_at' => '2026-11-08 09:45:00+01'],
            ['id' => $lesson->id()->toRfc4122()],
        );

        $this->jsonRequest('GET', $this->path($published));

        $this->assertJsonPath('can', [
            'edit' => false,
            'publish' => false,
            'unpublish' => true,
            'archive' => true,
            'restore' => false,
            'delete' => false,
            'viewProgress' => true,
        ]);
    }

    public function testParentsAnonymousCallersAndUnknownTasks(): void
    {
        self::mockTime('2026-11-08T10:00:00+01:00');
        $task = $this->task($this->lesson(), null);

        $this->jsonRequest('GET', $this->path($task));
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('hoda@example.org', 'parent');
        $this->jsonRequest('GET', $this->path($task));
        $this->assertProblem(403, 'forbidden');

        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('GET', '/api/homework/' . Uuid::v7()->toRfc4122());
        $this->assertProblem(404, 'notFound.homework');
    }

    private function path(HomeworkAssignment $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122();
    }

    private function roster(Lesson $lesson, int $size): void
    {
        $stage = new Database(self::getContainer())
            ->entityManager()
            ->find(Stage::class, $lesson->stageId());

        self::assertInstanceOf(Stage::class, $stage);
        $rosters = new Rosters(self::getContainer());

        foreach (range(start: 1, end: $size) as $number) {
            $rosters->place($rosters->person('Child ' . $number), $stage->service(), $stage, '2026-09-01');
        }
    }

    private function task(Lesson $lesson, ?string $dueDate): HomeworkAssignment
    {
        $task = HomeworkAssignment::draft(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it twice.', $dueDate === null ? null : LocalDate::of($dueDate)),
            Materials::of([['label' => 'Psalm 23', 'url' => 'https://example.org/psalm']], 'resources'),
            createdBy: null,
        );

        new Database(self::getContainer())->persisted($task);

        return $task;
    }

    private function lesson(): Lesson
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();

        new Database(self::getContainer())
            ->connection()
            ->update(
                'learning.lesson',
                ['passage' => 'Luke 10:25-37', 'memory_verse' => 'Love your neighbour.', 'notes' => 'Mina was sad'],
                ['id' => $lesson->id()->toRfc4122()],
            );

        new Database(self::getContainer())->entityManager()->clear();

        $reloaded = new Database(self::getContainer())
            ->entityManager()
            ->find(Lesson::class, $lesson->id());

        self::assertInstanceOf(Lesson::class, $reloaded);

        return $reloaded;
    }
}
