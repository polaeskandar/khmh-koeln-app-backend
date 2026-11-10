<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Validation;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Library\Problem\Exception\RuleViolation;

final readonly class MeetingLessonRule
{
    public static function ensureFreeToChoose(?Lesson $holder, ?Lesson $lesson): void
    {
        if ($holder !== null && $holder !== $lesson) {
            throw MeetingChoice::refusal('lessons.meetingHasLesson', ['title' => $holder->text()->title]);
        }
    }

    public static function ensureFreeToRestore(?Lesson $holder, Lesson $lesson): void
    {
        if ($holder !== null && $holder !== $lesson) {
            throw new RuleViolation('lessons.meetingHasLesson', ['title' => $holder->text()->title]);
        }
    }
}
