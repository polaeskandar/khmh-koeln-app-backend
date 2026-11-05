<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class HomeworkItems
{
    /**
     * @return list<HomeworkItem>
     */
    public static function of(TaskAudiences $audiences, AccessCheck $access, Stage $stage): array
    {
        $itemOf = static fn (HomeworkAssignment $task): HomeworkItem => self::itemOf(
            $audiences,
            $access,
            $stage,
            $task,
        );

        $newestFirst = Collections::sortedDescendingBy(
            new ArrayCollection($audiences->reports()->tasks),
            keyOf: self::sortKey(...),
        );

        return new ArrayCollection($newestFirst)->map($itemOf)->getValues();
    }

    public static function itemOf(
        TaskAudiences $audiences,
        AccessCheck $access,
        Stage $stage,
        HomeworkAssignment $task,
    ): HomeworkItem {
        return new HomeworkItem(
            HomeworkSummary::of($task, $audiences->reports()),
            LessonOnDate::of($task->lesson()),
            self::countsOf($audiences, $task),
            HomeworkAbilities::grantedBy($access, $task, $stage),
        );
    }

    public static function countsOf(TaskAudiences $audiences, HomeworkAssignment $task): ?AudienceCounts
    {
        return $task->state()->status === HomeworkStatus::Draft ? null : $audiences->countsOf($task);
    }

    private static function sortKey(HomeworkAssignment $task): string
    {
        $date = $task->text()->dueDate ?? $task->lesson()->link()->teachingDate;

        return $date->toString() . ' ' . $task->id()->toRfc4122();
    }
}
