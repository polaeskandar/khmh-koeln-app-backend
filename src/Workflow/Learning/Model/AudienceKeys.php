<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AudienceKeys
{
    /**
     * @return list<Uuid>
     */
    public static function stageIdsOf(TaskReports $reports): array
    {
        return new ArrayCollection($reports->tasks)->map(
            static fn (HomeworkTask $task): Uuid => $task->stageId(),
        )->getValues();
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return ReadableCollection<string, RosterKey>
     */
    public static function of(ReadableCollection $stages, LocalDate $today): ReadableCollection
    {
        return Collections::firstPerKey($stages, keyOf: self::stageKey(...))->map(
            static fn (Stage $stage): RosterKey => self::keyOf($stage, $today),
        );
    }

    public static function keyOf(Stage $stage, LocalDate $today): RosterKey
    {
        return new RosterKey($stage->service()->id(), $stage->id(), $today);
    }

    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
