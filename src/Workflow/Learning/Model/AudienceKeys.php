<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AudienceKeys
{
    /**
     * @return ReadableCollection<string, RosterKey>
     */
    public static function of(StructureLookup $structure, TaskReports $reports): ReadableCollection
    {
        $stageIds = new ArrayCollection($reports->tasks)->map(
            static fn (HomeworkAssignment $task): Uuid => $task->stageId(),
        );

        return Collections::firstPerKey($structure->stages($stageIds->getValues()), keyOf: self::stageKey(...))->map(
            static fn (Stage $stage): RosterKey => new RosterKey(
                $stage->service()->id(),
                $stage->id(),
                $reports->today,
            ),
        );
    }

    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
