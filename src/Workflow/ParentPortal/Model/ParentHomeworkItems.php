<?php

declare(strict_types=1);

namespace App\Workflow\ParentPortal\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\ValueObject\VisibleTask;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ParentHomeworkItems
{
    /**
     * @param ReadableCollection<string, Stage> $stages
     * @param ReadableCollection<string, User> $reporters
     */
    private function __construct(
        private ReadableCollection $stages,
        private ReadableCollection $reporters,
        private Uuid $viewerId,
    ) {
    }

    /**
     * @param list<VisibleTask> $tasks
     */
    public static function lookedUpIn(
        StructureLookup $structure,
        AccountLookup $accounts,
        array $tasks,
        Uuid $viewerId,
    ): self {
        $shown = new ArrayCollection($tasks);
        $stageIds = $shown->map(static fn (VisibleTask $visible): Uuid => $visible->task->stageId())->getValues();
        $reporterIds = Collections::mapPresent($shown, self::reporterIdOf(...))->getValues();

        return new self(
            Collections::firstPerKey($structure->stages($stageIds), keyOf: self::stageKey(...)),
            Collections::firstPerKey($accounts->accounts($reporterIds), keyOf: self::userKey(...)),
            $viewerId,
        );
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
                completedAt: $visible->completedAt,
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

    private static function userKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
