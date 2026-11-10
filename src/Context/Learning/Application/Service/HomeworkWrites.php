<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkDraft;
use App\Library\Problem\Exception\NotFound;
use App\Platform\Doctrine\Contract\VersionChecks;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkWrites
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private LessonRepositoryInterface $lessons,
        private VersionChecks $versions,
    ) {
    }

    public function getLessonToAssign(Uuid $lessonId): Lesson
    {
        $found = $this->lessons->find($lessonId) ?? throw NotFound::of('lesson');
        $found->state()->ensureEditable();

        return $found;
    }

    public function create(Lesson $lesson, HomeworkDraft $draft, Uuid $accountId): HomeworkAssignment
    {
        $content = $draft->contentFor($lesson, storedDueDate: null);
        $task = new HomeworkAssignment($lesson, $content->text, $content->resources, $accountId);
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
    public function whileEditable(Uuid $id, ?int $version, callable $work): object
    {
        return $this->whileTaskLocked($id, function (HomeworkAssignment $task) use ($version, $work): object {
            $this->versions->ensureCurrent($task, $version, 'homework.changedMeanwhile');
            $task->state()->ensureEditable();
            $task->lesson()->state()->ensureEditable();

            return $work($task);
        });
    }

    /**
     * @template T of object
     *
     * @param callable(HomeworkAssignment): T $work
     *
     * @return T
     */
    public function whileTaskLocked(Uuid $id, callable $work): object
    {
        return $this->homework->withWriteLock($id, $work) ?? throw NotFound::of('homework');
    }

    public function edit(HomeworkAssignment $task, HomeworkDraft $draft): HomeworkAssignment
    {
        $content = $draft->contentFor($task->lesson(), $task->text()->dueDate);
        $task->edit($content->text, $content->resources);
        $this->homework->save($task);

        return $task;
    }
}
