<?php

declare(strict_types=1);

namespace App\Tests\Functional\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkReport;
use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Time\Model\LocalDate;
use App\Tests\Support\Database;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class HomeworkTaskTest extends KernelTestCase
{
    #[DataProvider('violations')]
    public function testEachCheckRefusesItsRow(string $sqlState, string $set): void
    {
        $this->task();

        $this->database()->assertRefused($sqlState, 'UPDATE learning.homework_assignment SET ' . $set);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function violations(): iterable
    {
        yield 'published without a time' => [Database::CHECK_VIOLATION, "status = 'published'"];
        yield 'archived time on a draft' => [Database::CHECK_VIOLATION, 'archived_at = created_at'];
        yield 'archived without a time' => [Database::CHECK_VIOLATION, "status = 'archived'"];
        yield 'unknown status' => [Database::CHECK_VIOLATION, "status = 'graded'"];
        yield 'long instructions' => [Database::CHECK_VIOLATION, "instructions = repeat('a', 2001)"];
        yield 'blank instructions' => [Database::CHECK_VIOLATION, "instructions = '  '"];
        yield 'no instructions' => ['23502', 'instructions = NULL'];
        yield 'blank title' => [Database::CHECK_VIOLATION, "title = '   '"];
        yield 'eleven resources' => [
            Database::CHECK_VIOLATION,
            "resources = (SELECT jsonb_agg('{}'::jsonb) FROM generate_series(1, 11))",
        ];

        yield 'long title' => ['22001', "title = repeat('a', 121)"];
    }

    public function testAChildHasOneCurrentReportPerTaskAndUndoneOnesStay(): void
    {
        $task = $this->task();
        $child = Uuid::v7();
        $undone = new HomeworkReport($task, $child, new DateTimeImmutable('2026-11-16T18:00:00+01:00'), null);
        $undone->undo(new DateTimeImmutable('2026-11-16T18:05:00+01:00'), null);

        $this->database()->persisted(
            $undone,
            new HomeworkReport($task, $child, new DateTimeImmutable('2026-11-16T18:06:00+01:00'), null),
        );

        $this->database()->assertRefused(Database::UNIQUE_VIOLATION, fn () => $this->database()->persisted(
            new HomeworkReport($task, $child, new DateTimeImmutable('2026-11-16T18:07:00+01:00'), null),
        ));
    }

    public function testPublishedAndArchivedTasksAreStored(): void
    {
        $this->task();
        $connection = $this->database()->connection();

        $connection->executeStatement(
            "UPDATE learning.homework_assignment SET status = 'published', published_at = now()",
        );

        $connection->executeStatement(
            "UPDATE learning.homework_assignment SET status = 'archived', archived_at = now()",
        );

        $connection->executeStatement('UPDATE learning.homework_assignment SET published_at = NULL');

        self::assertSame('archived', $connection->fetchOne('SELECT status FROM learning.homework_assignment'));
    }

    public function testReportChecksHoldAndTheLessonCanNotBeDeleted(): void
    {
        $task = $this->task();

        $this->database()->persisted(
            new HomeworkReport($task, Uuid::v7(), new DateTimeImmutable('2026-11-16T18:00:00+01:00'), null),
        );

        $this->database()->assertRefused(
            Database::CHECK_VIOLATION,
            "UPDATE learning.homework_completion SET undone_by = '019a3b6e-0000-7000-8000-000000000009'",
        );

        $this->database()->assertRefused(
            Database::CHECK_VIOLATION,
            "UPDATE learning.homework_completion SET undone_at = completed_at - interval '1 minute'",
        );

        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM learning.lesson');
        $this->database()->assertRefused(Database::RESTRICT_VIOLATION, 'DELETE FROM learning.homework_assignment');
    }

    private function task(): HomeworkTask
    {
        $lesson = new Lesson(
            Uuid::v7(),
            new MeetingLink(null, LocalDate::of('2026-11-15')),
            new LessonText('Psalm 23'),
            null,
        );

        $task = new HomeworkTask(
            $lesson,
            HomeworkText::of('Learn the verse', 'Say it aloud.', null),
            Materials::none(),
            null,
        );

        $this->database()->persisted($lesson, $task);

        return $task;
    }

    private function database(): Database
    {
        return new Database(self::getContainer());
    }
}
