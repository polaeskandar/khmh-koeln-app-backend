<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Learning\Domain\ValueObject\PupilTasks;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ParentHomeworkOrder
{
    public const string DONE = 'done';

    private const string UNDATED = '9999-12-31';

    /**
     * @return list<VisibleTask>
     */
    public static function shown(PupilTasks $tasks, string $state): array
    {
        $done = $state === self::DONE;

        $shown = new ArrayCollection($tasks->tasks)->filter(
            static fn (VisibleTask $task): bool => $task->isDone() === $done,
        );

        if ($done) {
            return Collections::sortedDescendingBy($shown, keyOf: self::doneKey(...));
        }

        return Collections::sortedBy($shown, keyOf: self::toDoKey(...));
    }

    private static function toDoKey(VisibleTask $visible): string
    {
        $task = $visible->task;
        $publishedAt = $task->state()->publishedAt?->getTimestamp() ?? 0;

        return Text::format(
            '%s|%020d|%s',
            $task->text()->dueDate?->toString() ?? self::UNDATED,
            PHP_INT_MAX - $publishedAt,
            $task->id()->toRfc4122(),
        )->toString();
    }

    private static function doneKey(VisibleTask $visible): string
    {
        $completedAt = $visible->report?->completedAt()->getTimestamp() ?? 0;

        return Text::format('%020d|%s', $completedAt, $visible->task->id()->toRfc4122())->toString();
    }
}
