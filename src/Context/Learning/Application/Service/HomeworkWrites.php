<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkDraft;
use App\Library\Problem\Exception\NotFound;
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

    public function lessonToAssign(string $lessonId): Lesson
    {
        $lesson = $this->lessons->find(Uuid::fromString($lessonId)) ?? throw NotFound::of('lesson');
        $lesson->ensureEditable();

        return $lesson;
    }

    public function create(Lesson $lesson, HomeworkDraft $draft, string $accountId): HomeworkAssignment
    {
        $content = $draft->contentFor($lesson, stored: null);
        $task = HomeworkAssignment::draft($lesson, $content->text, $content->resources, Uuid::fromString($accountId));
        $this->homework->save($task);

        return $task;
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T
     */
    public function locked(string $id, callable $work): object
    {
        return $this->homework->withLock(Uuid::fromString($id), $work);
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T
     */
    public function whileEditable(string $id, ?int $version, callable $work): object
    {
        return $this->locked($id, function (HomeworkAssignment $task) use ($version, $work): object {
            $this->versions->ensureCurrent($task, $version, 'homework.changedMeanwhile');
            $task->ensureEditable();
            $task->lesson()->ensureEditable();

            return $work($task);
        });
    }

    public function edit(HomeworkAssignment $task, HomeworkDraft $draft): HomeworkAssignment
    {
        $content = $draft->contentFor($task->lesson(), $task);
        $task->edit($content->text, $content->resources);
        $this->homework->save($task);

        return $task;
    }

    public function publish(HomeworkAssignment $task): HomeworkAssignment
    {
        if ($task->publish($this->calendar->now())) {
            $this->homework->save($task);
        }

        return $task;
    }
}
