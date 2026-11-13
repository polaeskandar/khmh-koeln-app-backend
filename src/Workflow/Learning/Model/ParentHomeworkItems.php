<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentHomeworkItems
{
    /**
     * @param ReadableCollection<string, Stage> $stages
     */
    private function __construct(
        private ReadableCollection $stages,
        private NamedAccounts $reporters,
        private Uuid $viewerId,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(ReadableCollection $stages, NamedAccounts $reporters, Uuid $viewerId): self
    {
        return new self(Collections::firstPerKey($stages, keyOf: self::stageKey(...)), $reporters, $viewerId);
    }

    /**
     * @param list<VisibleTask> $tasks
     *
     * @return list<Uuid>
     */
    public static function stageIdsOf(array $tasks): array
    {
        return new ArrayCollection($tasks)->map(
            static fn (VisibleTask $visible): Uuid => $visible->task->stageId(),
        )->getValues();
    }

    /**
     * @param list<VisibleTask> $tasks
     *
     * @return list<Uuid>
     */
    public static function reporterIdsOf(array $tasks): array
    {
        return Collections::mapPresent(new ArrayCollection($tasks), self::reporterIdOf(...))->getValues();
    }

    /**
     * @param list<VisibleTask> $tasks
     *
     * @return list<ParentHomeworkItem>
     */
    public function listOf(array $tasks): array
    {
        return new ArrayCollection($tasks)->map($this->itemOf(...))->getValues();
    }

    public function itemOf(VisibleTask $visible): ParentHomeworkItem
    {
        $task = $visible->task;
        $reporterId = self::reporterIdOf($visible);
        $reporter = $this->reporters->find($reporterId);

        return new ParentHomeworkItem(
            ParentTaskView::of($task, $this->stages->get($task->stageId()->toRfc4122())?->name()->value),
            new ParentReportView(
                done: $visible->isDone(),
                completedAt: $visible->report?->completedAt(),
                completedByYou: $reporterId?->equals($this->viewerId) === true,
                completedBy: $reporter === null ? null : $this->reporters->nameOf($reporter),
            ),
        );
    }

    private static function reporterIdOf(VisibleTask $visible): ?Uuid
    {
        return $visible->report?->completedBy();
    }

    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
