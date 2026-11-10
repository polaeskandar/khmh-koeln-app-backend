<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Entity\Lesson;
use App\Context\Learning\Domain\Repository\HomeworkRepositoryInterface;
use App\Context\Learning\Domain\Repository\LessonHomeworkRepositoryInterface;
use App\Library\Time\Client\Calendar;
use App\Platform\Lifecycle\Contract\Transitions;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class HomeworkLifecycle
{
    public function __construct(
        private HomeworkRepositoryInterface $homework,
        private LessonHomeworkRepositoryInterface $lessonHomework,
        private Transitions $transitions,
        private Calendar $calendar,
        #[Target('homework_lifecycle')]
        private WorkflowInterface $lifecycle,
    ) {
    }

    public function publish(HomeworkAssignment $task): HomeworkAssignment
    {
        if ($this->transitions->apply($this->lifecycle, $task, 'publish')) {
            $task->changeState($task->state()->withPublishedAt($this->calendar->now()));
            $this->homework->save($task);
        }

        return $task;
    }

    public function unpublish(HomeworkAssignment $task): HomeworkAssignment
    {
        $this->transitions->apply($this->lifecycle, $task, 'unpublish');
        $this->homework->save($task);

        return $task;
    }

    public function archive(HomeworkAssignment $task): HomeworkAssignment
    {
        if ($this->archived($task, $this->calendar->now())) {
            $this->homework->save($task);
        }

        return $task;
    }

    public function archiveAllOf(Lesson $lesson, DateTimeImmutable $at): int
    {
        $unarchived = new ArrayCollection($this->lessonHomework->lockedOf($lesson->id()))->filter(
            static fn (HomeworkAssignment $task): bool => $task->state()->archivedAt === null,
        );

        foreach ($unarchived as $task) {
            $this->archived($task, $at);
            $this->homework->save($task);
        }

        return $unarchived->count();
    }

    public function restore(HomeworkAssignment $task): HomeworkAssignment
    {
        $transition = $task->state()->wasPublished() ? 'restore_as_published' : 'restore_as_draft';
        $this->transitions->apply($this->lifecycle, $task, $transition);
        $task->changeState($task->state()->withArchivedAt(null));
        $this->homework->save($task);

        return $task;
    }

    public function delete(HomeworkAssignment $task): HomeworkAssignment
    {
        $task->state()->ensureNeverPublished();
        $this->lessonHomework->remove($task);

        return $task;
    }

    private function archived(HomeworkAssignment $task, DateTimeImmutable $at): bool
    {
        if (!$this->transitions->apply($this->lifecycle, $task, 'archive')) {
            return false;
        }

        $task->changeState($task->state()->withArchivedAt($at));

        return true;
    }
}
