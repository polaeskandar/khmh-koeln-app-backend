<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonRepositoryInterface;
use App\Context\Learning\Domain\ValueObject\HomeworkDraft;
use App\Library\Problem\Exception\NotFound;
use Symfony\Component\Uid\Uuid;

final readonly class HomeworkWrites
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private LessonRepositoryInterface $lessons,
    ) {
    }

    public function lessonToAssign(string $lessonId): Lesson
    {
        $lesson = Uuid::isValid($lessonId) ? $this->lessons->find(Uuid::fromString($lessonId)) : null;
        $found = $lesson ?? throw NotFound::of('lesson');
        $found->state()->ensureEditable();

        return $found;
    }

    public function create(Lesson $lesson, HomeworkDraft $draft, string $accountId): HomeworkAssignment
    {
        $content = $draft->contentFor($lesson, storedDueDate: null);
        $task = new HomeworkAssignment($lesson, $content->text, $content->resources, Uuid::fromString($accountId));
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
    public function whileEditable(string $id, callable $work): object
    {
        $taskId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('homework');

        return $this->homework->withLock($taskId, static function (HomeworkAssignment $task) use ($work): object {
            $task->state()->ensureEditable();
            $task->lesson()->state()->ensureEditable();

            return $work($task);
        });
    }

    public function edit(HomeworkAssignment $task, HomeworkDraft $draft): HomeworkAssignment
    {
        $content = $draft->contentFor($task->lesson(), $task->text()->dueDate);
        $task->edit($content->text, $content->resources);
        $this->homework->save($task);

        return $task;
    }
}
