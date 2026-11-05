<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Library\Collections\Model\Collections;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class TaskReports
{
    /**
     * @param list<HomeworkAssignment> $tasks
     * @param list<HomeworkReport> $reports
     */
    public function __construct(
        public LocalDate $today,
        public array $tasks,
        private array $reports,
    ) {
    }

    /**
     * @return ReadableCollection<string, HomeworkReport>
     */
    public function of(HomeworkAssignment $task): ReadableCollection
    {
        $own = new ArrayCollection($this->reports)->filter(
            static fn (HomeworkReport $report): bool => $report->taskId->equals($task->id()),
        );

        return Collections::firstPerKey($own, keyOf: self::personKey(...));
    }

    /**
     * @return list<Uuid>
     */
    public function reporterIds(): array
    {
        return new ArrayCollection($this->reports)->map(self::personIdOf(...))->getValues();
    }

    /**
     * @return list<Uuid>
     */
    public function completerIds(): array
    {
        return Collections::mapPresent(
            new ArrayCollection($this->reports),
            static fn (HomeworkReport $report): ?Uuid => $report->completedBy,
        )->getValues();
    }

    private static function personIdOf(HomeworkReport $report): Uuid
    {
        return $report->personId;
    }

    private static function personKey(HomeworkReport $report): string
    {
        return $report->personId->toRfc4122();
    }
}
