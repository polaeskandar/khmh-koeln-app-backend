<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;

final readonly class LessonStatuses
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private Calendar $calendar,
    ) {
    }

    public function change(Lesson $lesson, string $status): Lesson
    {
        $chosen = LessonStatus::tryFrom($status) ?? throw InvalidInput::field('status', 'validation.invalidChoice');
        $state = $lesson->state()->withStatus($chosen, $this->calendar->now());

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
