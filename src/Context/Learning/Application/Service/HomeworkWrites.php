<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkDraft;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\VersionChecks;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkWrites
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private LessonRepositoryInterface $lessons,
        private VersionChecks $versions,
        private Calendar $calendar,
    ) {
    }

    public function getEditableLesson(string $lessonId): Lesson
    {
        $lesson = Uuid::isValid($lessonId) ? $this->lessons->find(Uuid::fromString($lessonId)) : null;
        $found = $lesson ?? throw NotFound::of('lesson');
        $found->state()->ensureEditable();

        return $found;
    }

    public function create(Lesson $lesson, HomeworkDraft $draft, string $accountId): HomeworkTask
    {
        $content = $draft->contentFor($lesson, storedDueDate: null);
        $task = new HomeworkTask($lesson, $content->text, $content->resources, Uuid::fromString($accountId));
        $this->homework->save($task);

        return $task;
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkTask): T $work
     *
     * @return T
     */
    public function whileEditable(string $id, ?int $version, callable $work): object
    {
        return $this->locked($id, function (HomeworkTask $task) use ($version, $work): object {
            $this->versions->ensureCurrent($task, $version, 'homework.changedMeanwhile');
            $task->state()->ensureEditable();
            $task->lesson()->state()->ensureEditable();

            return $work($task);
        });
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkTask): T $work
     *
     * @return T
     */
    public function locked(string $id, callable $work): object
    {
        $taskId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('homework');

        return $this->homework->withLock($taskId, $work);
    }

    public function publish(HomeworkTask $task): HomeworkTask
    {
        $state = $task->state()->published($this->calendar->now());

        if ($task->lesson()->state()->archivedAt !== null) {
            throw new RuleViolation('homework.lessonArchived');
        }

        if ($state !== $task->state()) {
            $task->changeState($state);
            $this->homework->save($task);
        }

        return $task;
    }

    public function edit(HomeworkTask $task, HomeworkDraft $draft): HomeworkTask
    {
        $content = $draft->contentFor($task->lesson(), $task->text()->dueDate);
        $task->edit($content->text, $content->resources);
        $this->homework->save($task);

        return $task;
    }
}
