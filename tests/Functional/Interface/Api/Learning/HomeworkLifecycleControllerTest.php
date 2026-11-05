<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class HomeworkLifecycleControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string PUBLISHED = "status = 'published', published_at = '2026-11-01 09:00:00+01'";

    private const string ARCHIVED = "status = 'archived', archived_at = '2026-11-02 09:00:00+01'";

    private const array ACTIONS = ['unpublish', 'archive', 'restore', 'delete'];

    public function testUnpublishingKeepsThePublishTimeAndStopsAtCurrentReports(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $free = $this->task($lesson, self::PUBLISHED);
        $reported = $this->task($lesson, self::PUBLISHED);
        $undone = $this->task($lesson, self::PUBLISHED);
        $reports = new Reports(self::getContainer());
        $families = new Families(self::getContainer());
        $reports->stored($reported, $families->child('Mina'));
        $reports->stored($undone, $families->child('Karim'), undoneAt: '2026-11-07T11:00:00+01:00');
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->act($free, 'unpublish');
        self::assertResponseIsSuccessful();

        self::assertSame(
            [
                'id' => $free->id()->toRfc4122(),
                'status' => 'draft',
                'publishedAt' => '2026-11-01T09:00:00+01:00',
                'archivedAt' => null,
            ],
            $this->responseJson(),
        );

        $this->act($free, 'unpublish');
        $this->assertProblem(409, 'homework.onlyPublishedBack');
        $this->jsonRequest('DELETE', $this->path($free));
        $this->assertProblem(409, 'homework.publishedStays');
        $this->act($reported, 'unpublish');
        $this->assertProblem(409, 'homework.alreadyReported');
        $this->act($undone, 'unpublish');
        $this->assertJsonPath('status', 'draft');
    }

    public function testAReportFromAChildOutsideTheStageStillBlocksUnpublishing(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $task = $this->task($lesson, self::PUBLISHED);
        new Reports(self::getContainer())->stored($task, new Families(self::getContainer())->child('Mina'));
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->act($task, 'unpublish');
        $this->assertProblem(409, 'homework.alreadyReported');
    }

    public function testArchivingKeepsTheFirstTimeAndTheReportsAndRestoringReturnsTheFormerStatus(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $draft = $this->task($lesson, '');
        $published = $this->task($lesson, self::PUBLISHED);
        new Reports(self::getContainer())->stored($published, new Families(self::getContainer())->child('Mina'));
        $connection = new Database(self::getContainer())->connection();
        $this->signInWithRoles('daniel@example.org', 'servant');

        foreach ([$draft, $published] as $task) {
            $this->act($task, 'archive');
            $this->assertJsonPath('status', 'archived');
            $this->assertJsonPath('archivedAt', '2026-11-08T12:00:00+01:00');
        }

        self::mockTime('2026-11-08T13:00:00+01:00');
        $this->act($published, 'archive');
        $this->assertJsonPath('archivedAt', '2026-11-08T12:00:00+01:00');
        self::assertSame(1, Integers::of($connection->fetchOne('SELECT count(*) FROM learning.homework_completion')));

        $this->act($published, 'restore');
        $this->assertJsonPath('status', 'published');
        $this->assertJsonPath('publishedAt', '2026-11-01T09:00:00+01:00');
        $this->assertJsonPath('archivedAt', null);
        $this->act($draft, 'restore');
        $this->assertJsonPath('status', 'draft');
        $this->act($draft, 'restore');
        $this->assertProblem(409, 'homework.notArchived');
        $this->jsonRequest('DELETE', $this->path($draft));
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, Integers::of($connection->fetchOne('SELECT count(*) FROM learning.homework_assignment')));
    }

    public function testRestoringNeedsTheLessonAndArchivedStagesRefuseEverything(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $task = $this->task($lesson, self::ARCHIVED);
        $archived = ['archived_at' => '2026-11-02 09:00:00+01'];
        $connection = new Database(self::getContainer())->connection();
        $connection->update('learning.lesson', $archived, ['id' => $lesson->id()->toRfc4122()]);
        $this->signInWithRoles('daniel@example.org', 'servant');

        $this->act($task, 'restore');
        $this->assertProblem(409, 'homework.lessonArchived');

        $connection->update('organization.stage', $archived, ['id' => $lesson->stageId()->toRfc4122()]);

        foreach (['unpublish', 'archive', 'restore'] as $action) {
            $this->act($task, $action);
            $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);
        }

        $this->jsonRequest('DELETE', $this->path($task));
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);
    }

    public function testParentsAnonymousCallersAndUnknownTasks(): void
    {
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass(), self::PUBLISHED);

        $this->act($task, 'unpublish');
        $this->assertProblem(401, 'auth.unauthenticated');

        $this->signInWithRoles('hoda@example.org', 'parent');

        foreach (self::ACTIONS as $action) {
            $this->act($task, $action);
            $this->assertProblem(403, 'homework.changeStageOnly');
        }

        $this->browser()->getCookieJar()->clear();
        $this->signInWithRoles('daniel@example.org', 'servant');
        $this->jsonRequest('POST', '/api/homework/' . Uuid::v7()->toRfc4122() . '/archive', []);
        $this->assertProblem(404, 'notFound.homework');
        $this->jsonRequest('POST', '/api/homework/not-a-task/restore', []);
        $this->assertProblem(404, 'notFound.homework');
        $this->jsonRequest('DELETE', '/api/homework/not-a-task');
        $this->assertProblem(404, 'notFound.homework');
    }

    private function act(HomeworkAssignment $task, string $action): void
    {
        if ($action === 'delete') {
            $this->jsonRequest('DELETE', $this->path($task));

            return;
        }

        $this->jsonRequest('POST', $this->path($task) . '/' . $action, []);
    }

    private function path(HomeworkAssignment $task): string
    {
        return '/api/homework/' . $task->id()->toRfc4122();
    }

    private function task(Lesson $lesson, string $set): HomeworkAssignment
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
            . ' WHERE id = ?', [
                $task->id()->toRfc4122(),
            ]);
        }

        return $task;
    }
}
