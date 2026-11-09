<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\ArchivedLesson;
use App\Context\Learning\Domain\ValueObject\MeetingHolder;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\Transactions;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class LessonArchives
{
    public function __construct(
        private LessonRepositoryInterface $lessons,
        private LessonHomeworkRepositoryInterface $homework,
        private Transactions $transactions,
        private Calendar $calendar,
    ) {
    }

    public function get(string $id): Lesson
    {
        $lesson = Uuid::isValid($id) ? $this->lessons->find(Uuid::fromString($id)) : null;

        return $lesson ?? throw NotFound::of('lesson');
    }

    public function archive(Lesson $lesson): ArchivedLesson
    {
        $at = $this->calendar->now();

        return $this->transactions->run(function () use ($lesson, $at): ArchivedLesson {
            if ($lesson->state()->archivedAt !== null) {
                return new ArchivedLesson($lesson, homeworkArchived: 0);
            }

            $lesson->archive($at);
            $archived = new ArrayCollection($this->homework->lockedOf($lesson->id()))->filter(
                static fn (HomeworkTask $task): bool => $task->state()->archivedAt === null,
            );

            foreach ($archived as $task) {
                $task->changeState($task->state()->archived($at));
            }

            $this->lessons->save($lesson);

            return new ArchivedLesson($lesson, $archived->count());
        });
    }

    public function restore(Lesson $lesson): Lesson
    {
        $link = $lesson->link();
        $holder = $link->scheduleId === null ? null : $this->lessons->currentAt($link->scheduleId, $link->teachingDate);
        MeetingHolder::ensureFreeFor($lesson, $holder);
        $lesson->changeState($lesson->state()->restored());
        $this->lessons->save($lesson);

        return $lesson;
    }
}
