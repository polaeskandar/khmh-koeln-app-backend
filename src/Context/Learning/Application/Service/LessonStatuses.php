<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class LessonStatuses
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private Calendar $calendar,
    ) {
    }

    public function change(Lesson $lesson, LessonStatus $status): Lesson
    {
        $state = $lesson->state()->withStatus($status, $this->calendar->now());

        if ($state === $lesson->state()) {
            return $lesson;
        }

        if ($state->isCompletedAfter($lesson->link()->teachingDate, $this->calendar->today())) {
            throw new RuleViolation('lessons.taughtAfterDate');
        }

        $lesson->changeState($state);
        $this->lessons->save($lesson);

        return $lesson;
    }
}
