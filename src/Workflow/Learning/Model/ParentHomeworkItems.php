<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Domain\Entity\Account;
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
     * @param ReadableCollection<string, Account> $reporters
     */
    private function __construct(
        private ReadableCollection $stages,
        private ReadableCollection $reporters,
        private Uuid $viewerId,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     * @param ReadableCollection<int, Account> $reporters
     */
    public static function of(ReadableCollection $stages, ReadableCollection $reporters, Uuid $viewerId): self
    {
        return new self(
            Collections::firstPerKey($stages, keyOf: self::stageKey(...)),
            Collections::firstPerKey($reporters, keyOf: self::accountKey(...)),
            $viewerId,
        );
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

        return new ParentHomeworkItem(
            ParentTaskView::of($task, $this->stages->get($task->stageId()->toRfc4122())?->name()->value),
            new ParentReportView(
                done: $visible->isDone(),
                completedAt: $visible->report?->completedAt(),
                completedByYou: $reporterId?->equals($this->viewerId) === true,
                completedBy: $this->reporters->get($reporterId?->toRfc4122() ?? '')?->profile()->displayName->value,
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

    private static function accountKey(Account $account): string
    {
        return $account->id()->toRfc4122();
    }
}
