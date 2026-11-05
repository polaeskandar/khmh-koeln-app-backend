<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Library\Problem\Exception\RuleViolation;

final readonly class MeetingHolder
{
    public static function ensureFreeFor(Lesson $lesson, ?Lesson $holder): void
    {
        if ($holder !== null && $holder !== $lesson) {
            throw new RuleViolation('lessons.meetingHasLesson', ['title' => $holder->text()->title]);
        }
    }
}
