<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Learning\Domain\Entity;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\HomeworkText;
use App\Context\Learning\Domain\ValueObject\LessonText;
use App\Context\Learning\Domain\ValueObject\Materials;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class HomeworkAssignmentTest extends TestCase
{
    public function testANewTaskTakesTheLessonsStageAndStartsAsADraft(): void
    {
        $stage = Uuid::v7();

        $lesson = new Lesson(
            $stage,
            new MeetingLink(null, LocalDate::of('2026-11-15')),
            new LessonText('Psalm 23'),
            null,
        );

        $task = new HomeworkAssignment(
            $lesson,
            HomeworkText::of("  Learn   the\tverse ", "  Read it twice.\nThen say it aloud.  ", null),
            Materials::none(),
            createdBy: null,
        );

        self::assertSame($stage, $task->stageId());
        self::assertSame('Learn the verse', $task->text()->title);
        self::assertSame("Read it twice.\nThen say it aloud.", $task->text()->instructions);
        self::assertSame(HomeworkStatus::Draft, $task->state()->status);
    }

    public function testOnlyAPublishedTaskIsVisibleToParentsAndARepublishKeepsItsTime(): void
    {
        $task = new HomeworkAssignment(
            new Lesson(
                Uuid::v7(),
                new MeetingLink(null, LocalDate::of('2026-11-15')),
                new LessonText('Psalm 23'),
                null,
            ),
            HomeworkText::of('Learn the verse', 'Read it twice.', null),
            Materials::none(),
            createdBy: null,
        );

        self::assertFalse($task->isVisibleToParents());

        $published = $task->state()->published(new DateTimeImmutable('2026-11-08T10:00:00+01:00'));
        $task->changeState($published);
        self::assertSame($published, $published->published(new DateTimeImmutable('2026-11-09T10:00:00+01:00')));

        self::assertTrue($task->isVisibleToParents());
        self::assertSame('2026-11-08T10:00:00+01:00', $task->state()->publishedAt?->format(DATE_ATOM));
    }
}
