<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Enum\LessonStatus;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\Validation\MeetingLessonRule;
use App\Context\Learning\Domain\ValueObject\ArchivedLesson;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Symfony\Component\Uid\Uuid;

final readonly class LessonLifecycle
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private HomeworkLifecycle $homework,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function get(Uuid $id): Lesson
    {
        return $this->lessons->find($id) ?? throw NotFound::of('lesson');
    }

    public function changeStatus(Lesson $lesson, LessonStatus $status): Lesson
    {
        $state = $lesson->state()->withStatus($status, $this->calendar->now());

        if ($state === $lesson->state()) {
            return $lesson;
        }

        if ($state->isCompletedBeforeTeachingDate($lesson->link()->teachingDate, $this->calendar->today())) {
            throw new RuleViolation('lessons.taughtAfterDate');
        }

        $lesson->changeState($state);
        $this->lessons->save($lesson);

        return $lesson;
    }

    public function archive(Lesson $lesson): ArchivedLesson
    {
        $at = $this->calendar->now();

        return $this->transactions->run(function () use ($lesson, $at): ArchivedLesson {
            if ($lesson->state()->archivedAt !== null) {
                return new ArchivedLesson($lesson, homeworkArchived: 0);
            }

            $lesson->archive($at);
            $homeworkArchived = $this->homework->archiveAllOf($lesson, $at);
            $this->lessons->save($lesson);

            return new ArchivedLesson($lesson, $homeworkArchived);
        });
    }

    public function restore(Lesson $lesson): Lesson
    {
        $link = $lesson->link();
        $holder = $link->scheduleId === null ? null : $this->lessons->currentAt($link->scheduleId, $link->teachingDate);
        MeetingLessonRule::ensureFreeToRestore($holder, $lesson);
        $lesson->changeState($lesson->state()->restored());
        $this->lessons->save($lesson);

        return $lesson;
    }
}
