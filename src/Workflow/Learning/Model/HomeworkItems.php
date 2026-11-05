<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\Enum\HomeworkStatus;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class HomeworkItems
{
    /**
     * @return list<HomeworkItem>
     */
    public static function of(TaskReports $reports, TaskAudiences $audiences): array
    {
        $itemOf = static fn (HomeworkAssignment $task): HomeworkItem => self::itemOf($audiences, $task);

        $newestFirst = Orderings::byKeysDescending(new ArrayCollection($reports->tasks), keysOf: self::sortKeys(...));

        return new ArrayCollection($newestFirst)->map($itemOf)->getValues();
    }

    public static function itemOf(TaskAudiences $audiences, HomeworkAssignment $task): HomeworkItem
    {
        return new HomeworkItem(
            HomeworkSummary::of($task),
            LessonOnDate::of($task->lesson()),
            self::countsOf($audiences, $task),
        );
    }

    public static function countsOf(TaskAudiences $audiences, HomeworkAssignment $task): ?AudienceCounts
    {
        return $task->state()->status === HomeworkStatus::Draft ? null : $audiences->countsOf($task);
    }

    /**
     * @return list<string>
     */
    private static function sortKeys(HomeworkAssignment $task): array
    {
        $date = $task->text()->dueDate ?? $task->lesson()->link()->teachingDate;

        return [$date->toString(), $task->id()->toRfc4122()];
    }
}
