<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Types\Model\Type;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Context\Learning\Domain\Entity\HomeworkTasks;
use App\Tests\Support\Context\Learning\Domain\Entity\Lessons;
use App\Tests\Support\Context\Learning\Domain\Entity\Reports;
use App\Tests\Support\Context\People\Domain\Entity\Families;
use App\Tests\Support\Database;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomeworkAssignmentLifecycleTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    private const string NOW = '2026-11-08 12:00:00+01';

    private const string PUBLISHED_AT = '2026-11-01 09:00:00+01';

    private const string ARCHIVED_AT = '2026-11-02 09:00:00+01';

    private const string DRAFT = '';

    private const string UNPUBLISHED = "published_at = '" . self::PUBLISHED_AT . "'";

    private const string PUBLISHED = "status = 'published', " . self::UNPUBLISHED;

    private const string ARCHIVED_DRAFT = "status = 'archived', archived_at = '" . self::ARCHIVED_AT . "'";

    private const string ARCHIVED_PUBLISHED = self::ARCHIVED_DRAFT . ', ' . self::UNPUBLISHED;

    private const string ROW = <<<'SQL'
        SELECT status, published_at::text AS published_at, archived_at::text AS archived_at, version::text AS version
        FROM learning.homework_assignment WHERE id = ?
        SQL;

    /**
     * @param ?array<string, ?string> $row
     */
    #[DataProvider('transitions')]
    public function testEveryStateMeetsEveryTransition(
        string $state,
        string $action,
        int $status,
        ?string $answer,
        ?array $row,
    ): void {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass(), $state);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($task, $action);

        self::assertResponseStatusCodeSame($status);
        self::assertSame($answer, $this->answer());
        self::assertSame($row, $this->stored($task));
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, ?array<string, ?string>}>
     */
    public static function transitions(): iterable
    {
        $draft = self::row('draft', publishedAt: null, archivedAt: null, version: '1');
        $unpublished = self::row('draft', self::PUBLISHED_AT, archivedAt: null, version: '1');
        $published = self::row('published', self::PUBLISHED_AT, archivedAt: null, version: '1');
        $archivedDraft = self::row('archived', publishedAt: null, archivedAt: self::ARCHIVED_AT, version: '1');
        $archivedPublished = self::row('archived', self::PUBLISHED_AT, self::ARCHIVED_AT, version: '1');
        $publishedNow = self::row('published', self::NOW, archivedAt: null, version: '2');

        yield 'draft, publish' => [self::DRAFT, 'publish', 200, 'published', $publishedNow];
        yield 'draft, unpublish' => [self::DRAFT, 'unpublish', 409, 'homework.onlyPublishedBack', $draft];
        yield 'draft, archive' => [
            self::DRAFT,
            'archive',
            200,
            'archived',
            self::row('archived', publishedAt: null, archivedAt: self::NOW, version: '2'),
        ];

        yield 'draft, restore' => [self::DRAFT, 'restore', 409, 'homework.notArchived', $draft];
        yield 'draft, delete' => [self::DRAFT, 'delete', 204, null, null];
        yield 'unpublished, publish again' => [self::UNPUBLISHED, 'publish', 200, 'published', $publishedNow];
        yield 'unpublished, unpublish' => [
            self::UNPUBLISHED,
            'unpublish',
            409,
            'homework.onlyPublishedBack',
            $unpublished,
        ];

        yield 'unpublished, archive' => [
            self::UNPUBLISHED,
            'archive',
            200,
            'archived',
            self::row('archived', self::PUBLISHED_AT, self::NOW, version: '2'),
        ];

        yield 'unpublished, restore' => [self::UNPUBLISHED, 'restore', 409, 'homework.notArchived', $unpublished];
        yield 'unpublished, delete' => [self::UNPUBLISHED, 'delete', 409, 'homework.publishedStays', $unpublished];
        yield 'published, publish again' => [self::PUBLISHED, 'publish', 200, 'published', $published];

        yield 'published, unpublish' => [
            self::PUBLISHED,
            'unpublish',
            200,
            'draft',
            self::row('draft', self::PUBLISHED_AT, archivedAt: null, version: '2'),
        ];

        yield 'published, archive' => [
            self::PUBLISHED,
            'archive',
            200,
            'archived',
            self::row('archived', self::PUBLISHED_AT, self::NOW, version: '2'),
        ];

        yield 'published, restore' => [self::PUBLISHED, 'restore', 409, 'homework.notArchived', $published];
        yield 'published, delete' => [self::PUBLISHED, 'delete', 409, 'homework.publishedStays', $published];

        yield 'archived draft, publish' => [
            self::ARCHIVED_DRAFT,
            'publish',
            409,
            'homework.archivedNoPublish',
            $archivedDraft,
        ];

        yield 'archived draft, unpublish' => [
            self::ARCHIVED_DRAFT,
            'unpublish',
            409,
            'homework.onlyPublishedBack',
            $archivedDraft,
        ];

        yield 'archived draft, archive again' => [self::ARCHIVED_DRAFT, 'archive', 200, 'archived', $archivedDraft];

        yield 'archived draft, restore' => [
            self::ARCHIVED_DRAFT,
            'restore',
            200,
            'draft',
            self::row('draft', publishedAt: null, archivedAt: null, version: '2'),
        ];

        yield 'archived draft, delete' => [self::ARCHIVED_DRAFT, 'delete', 204, null, null];

        yield 'archived published, publish' => [
            self::ARCHIVED_PUBLISHED,
            'publish',
            409,
            'homework.archivedNoPublish',
            $archivedPublished,
        ];

        yield 'archived published, unpublish' => [
            self::ARCHIVED_PUBLISHED,
            'unpublish',
            409,
            'homework.onlyPublishedBack',
            $archivedPublished,
        ];

        yield 'archived published, archive again' => [
            self::ARCHIVED_PUBLISHED,
            'archive',
            200,
            'archived',
            $archivedPublished,
        ];

        yield 'archived published, restore' => [
            self::ARCHIVED_PUBLISHED,
            'restore',
            200,
            'published',
            self::row('published', self::PUBLISHED_AT, archivedAt: null, version: '2'),
        ];

        yield 'archived published, delete' => [
            self::ARCHIVED_PUBLISHED,
            'delete',
            409,
            'homework.publishedStays',
            $archivedPublished,
        ];
    }

    public function testACurrentReportStopsOnlyUnpublishingAndOnlyAfterTheStatusCheck(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $published = $this->task($lesson, self::PUBLISHED);
        $draft = $this->task($lesson, self::DRAFT);
        $families = new Families(self::getContainer());
        new Reports(self::getContainer())->stored($published, $families->child('Mina'));
        new Reports(self::getContainer())->stored($draft, $families->child('Karim'));
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($published, 'unpublish');
        $this->assertProblem(409, 'homework.alreadyReported');
        $this->act($draft, 'unpublish');
        $this->assertProblem(409, 'homework.onlyPublishedBack');

        $this->act($published, 'archive');
        self::assertSame('archived', $this->answer());
        $this->act($published, 'restore');

        self::assertSame(
            self::row('published', self::PUBLISHED_AT, archivedAt: null, version: '3'),
            $this->stored($published),
        );
    }

    public function testAnArchivedLessonStopsPublishingAndRestoringAfterTheStatusChecks(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $lesson = new Lessons(self::getContainer())->inThirdClass();
        $draft = $this->task($lesson, self::DRAFT);
        $published = $this->task($lesson, self::PUBLISHED);
        $archived = $this->task($lesson, self::ARCHIVED_PUBLISHED);
        $this->archive($lesson);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($draft, 'publish');
        $this->assertProblem(409, 'homework.lessonArchived');
        $this->act($published, 'publish');
        $this->assertProblem(409, 'homework.lessonArchived');
        $this->act($archived, 'publish');
        $this->assertProblem(409, 'homework.archivedNoPublish');
        $this->act($archived, 'restore');
        $this->assertProblem(409, 'homework.lessonArchived');
        $this->act($draft, 'restore');
        $this->assertProblem(409, 'homework.notArchived');

        $this->act($published, 'unpublish');
        self::assertSame('draft', $this->answer());
        $this->act($published, 'archive');
        self::assertSame('archived', $this->answer());
    }

    public function testATaskUnpublishedAndThenArchivedIsRestoredAsPublished(): void
    {
        self::mockTime('2026-11-08T12:00:00+01:00');
        $task = $this->task(new Lessons(self::getContainer())->inThirdClass(), self::PUBLISHED);
        $this->signInWithRoles('admin@example.org', 'administrator');

        $this->act($task, 'unpublish');
        $this->act($task, 'archive');
        $this->act($task, 'restore');

        self::assertSame(
            self::row('published', self::PUBLISHED_AT, archivedAt: null, version: '4'),
            $this->stored($task),
        );
    }

    /**
     * @return array<string, ?string>
     */
    private static function row(string $status, ?string $publishedAt, ?string $archivedAt, string $version): array
    {
        return [
            'status' => $status,
            'published_at' => $publishedAt,
            'archived_at' => $archivedAt,
            'version' => $version,
        ];
    }

    private function task(Lesson $lesson, string $state): HomeworkAssignment
    {
        $task = new HomeworkTasks(self::getContainer())->draft($lesson, dueDate: null);
        $database = new Database(self::getContainer());

        if ($state !== self::DRAFT) {
            $database->connection()->executeStatement('UPDATE learning.homework_assignment SET '
            . $state
            . ' WHERE id = ?', [$task->id()->toRfc4122()]);
        }

        return $task;
    }

    private function archive(Lesson $lesson): void
    {
        new Database(self::getContainer())
            ->connection()
            ->update('learning.lesson', ['archived_at' => self::ARCHIVED_AT], ['id' => $lesson->id()->toRfc4122()]);
    }

    private function act(HomeworkAssignment $task, string $action): void
    {
        $path = '/api/homework/' . $task->id()->toRfc4122();

        if ($action === 'delete') {
            $this->jsonRequest('DELETE', $path);

            return;
        }

        $this->jsonRequest('POST', $path . '/' . $action);
    }

    private function answer(): ?string
    {
        if ($this->browser()->getResponse()->getContent() === '') {
            return null;
        }

        $body = $this->responseJson();

        return Type::of($body['code'] ?? $body['status'] ?? null)->toStringOrNull();
    }

    /**
     * @return ?array<string, ?string>
     */
    private function stored(HomeworkAssignment $task): ?array
    {
        $row = new Database(self::getContainer())
            ->connection()
            ->fetchAssociative(self::ROW, [$task->id()->toRfc4122()]);

        return $row === false
            ? null
            : array_map(static fn (mixed $value): ?string => Type::of($value)->toStringOrNull(), $row);
    }
}
