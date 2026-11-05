<?php

declare(strict_types=1);

namespace App\Tests\Functional\Interface\Api\Learning;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Time\Model\LocalDate;
use App\Library\Types\Model\Integers;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class LessonLifecycleControllerTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    public function testArchivingTakesTheHomeworkAlongAtOneInstantAndKeepsTheReports(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $published = [$this->publishedTask($lesson), $this->publishedTask($lesson)];
        $this->draftTask($lesson);
        new Reports(self::getContainer())->stored($published[0], new Families(self::getContainer())->child('Mina'));
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->post($lesson, 'archive');

        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archivedAt', '2026-11-08T12:00:00+01:00');
        $this->assertJsonPath('homeworkArchived', 3);

        self::assertSame(
            1,
            $this->numberOf(
                "SELECT count(DISTINCT archived_at) FROM learning.homework_assignment WHERE archived_at = '2026-11-08 12:00:00+01'",
            ),
        );

        self::assertSame(
            3,
            $this->numberOf('SELECT count(*) FROM learning.homework_assignment WHERE archived_at IS NOT NULL'),
        );

        self::assertSame(1, $this->numberOf('SELECT count(*) FROM learning.homework_completion'));

        self::mockTime('2026-11-08T13:00:00+01:00');
        $this->post($lesson, 'archive');
        $this->assertJsonPath('archivedAt', '2026-11-08T12:00:00+01:00');
        $this->assertJsonPath('homeworkArchived', 0);
    }

    public function testRestoringKeepsTheHomeworkArchivedAndRespectsTheMeeting(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $lessons = new Lessons(self::getContainer());
        $lesson = $lessons->inThirdClass();
        $this->publishedTask($lesson);
        $this->signInWithRoles('servant@example.org', 'servant');
        $this->post($lesson, 'archive');

        $this->post($lesson, 'restore');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archivedAt', null);
        $this->post($lesson, 'restore');
        self::assertResponseIsSuccessful();
        $this->assertJsonPath('archivedAt', null);

        self::assertSame(
            1,
            $this->numberOf('SELECT count(*) FROM learning.homework_assignment WHERE archived_at IS NOT NULL'),
        );

        $this->post($lesson, 'archive');
        $this->sameMeeting($lesson, 'Daniel in the den');
        $this->post($lesson, 'restore');
        $this->assertProblem(409, 'lessons.meetingHasLesson', ['title' => 'Daniel in the den']);
    }

    public function testOnlyDraftsWithoutPublishedHomeworkAreDeleted(): void
    {
        $lessons = new Lessons(self::getContainer());
        $empty = $lessons->inThirdClass('2026-11-15');
        $withDraftTask = $this->sibling($empty, '2026-11-22');
        $this->draftTask($withDraftTask);
        $ready = $this->sibling($empty, '2026-11-29');

        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['status' => 'ready'], ['id' => $ready->id()->toRfc4122()]);

        $published = $this->sibling($empty, '2026-12-06');
        $this->publishedTask($published);
        $this->signInWithRoles('servant@example.org', 'servant');

        $this->jsonRequest('DELETE', '/api/lessons/' . $empty->id()->toRfc4122());
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('GET', '/api/lessons/' . $empty->id()->toRfc4122());
        $this->assertProblem(404, 'notFound.lesson');
        $this->jsonRequest('DELETE', '/api/lessons/' . $withDraftTask->id()->toRfc4122());
        self::assertResponseStatusCodeSame(204);
        $this->jsonRequest('DELETE', '/api/lessons/' . $ready->id()->toRfc4122());
        $this->assertProblem(409, 'lessons.deleteDraftsOnly');
        $this->jsonRequest('DELETE', '/api/lessons/' . $published->id()->toRfc4122());
        $this->assertProblem(409, 'lessons.homeworkPublished');
        self::assertSame(1, $this->numberOf('SELECT count(*) FROM learning.homework_assignment'));
    }

    public function testParentsAndArchivedStagesAreRefused(): void
    {
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $this->signInWithRoles('parent@example.org', 'parent');

        foreach (['archive', 'restore'] as $action) {
            $this->post($lesson, $action);
            $this->assertProblem(403, 'lessons.changeStageOnly');
        }

        $this->jsonRequest('DELETE', '/api/lessons/' . $lesson->id()->toRfc4122());
        $this->assertProblem(403, 'lessons.changeStageOnly');

        $this->browser()->getCookieJar()->clear();

        new Database(self::getContainer())
            ->connection()
            ->executeStatement('UPDATE organization.stage SET archived_at = now()');

        $this->signInWithRoles('servant@example.org', 'servant');
        $this->post($lesson, 'archive');
        $this->assertProblem(409, 'lessons.stageArchived', ['name' => '3rd Class']);

        foreach ([['POST', '/archive'], ['POST', '/restore'], ['DELETE', '']] as [$method, $suffix]) {
            $this->jsonRequest($method, '/api/lessons/not-a-lesson' . $suffix);
            $this->assertProblem(404, 'notFound.lesson');
        }
    }

    private function post(Lesson $lesson, string $action): void
    {
        $this->jsonRequest('POST', '/api/lessons/' . $lesson->id()->toRfc4122() . '/' . $action);
    }

    private function draftTask(Lesson $lesson): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            null,
        );

        new Database(self::getContainer())->persisted($task);

        return $task;
    }

    private function publishedTask(Lesson $lesson): HomeworkAssignment
    {
        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of('Learn the verse', 'Read it.', null),
            Materials::none(),
            null,
        );

        $task->changeState($task->state()->published(new DateTimeImmutable('2026-11-01T09:00:00+01:00')));
        new Database(self::getContainer())->persisted($task);

        return $task;
    }

    private function sibling(Lesson $lesson, string $date): Lesson
    {
        $copy = new Lesson(
            $lesson->stageId(),
            new MeetingLink(null, LocalDate::of($date)),
            new LessonText('Lesson ' . $date),
            responsibleId: null,
        );

        new Database(self::getContainer())->persisted($copy);

        return $copy;
    }

    private function sameMeeting(Lesson $lesson, string $title): void
    {
        $link = $lesson->link();
        $other = new Lesson($lesson->stageId(), $link, new LessonText($title), responsibleId: null);
        new Database(self::getContainer())->persisted($other);
    }

    private function numberOf(string $sql): int
    {
        return Integers::of(
            new Database(self::getContainer())
                ->connection()
                ->fetchOne($sql),
        );
    }
}
