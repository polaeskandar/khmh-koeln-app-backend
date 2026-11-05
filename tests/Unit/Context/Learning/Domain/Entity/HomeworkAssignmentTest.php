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

        $task = HomeworkAssignment::draft(
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
}
