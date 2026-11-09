<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\Entity\HomeworkTask;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class TaskAudiences
{
    /**
     * @param ReadableCollection<string, RosterKey> $keys
     */
    private function __construct(
        private TaskReports $reports,
        private ReadableCollection $keys,
        private RosterBook $rosters,
        private AudienceAccounts $accounts,
    ) {
    }

    /**
     * @param ReadableCollection<string, RosterKey> $keys
     */
    public static function of(
        TaskReports $reports,
        ReadableCollection $keys,
        RosterBook $book,
        AudienceAccounts $accounts,
    ): self {
        return new self($reports, $keys, $book, $accounts);
    }

    /**
     * @return list<Uuid>
     */
    public static function accountIdsOf(RosterBook $book, TaskReports $reports): array
    {
        return [...$book->guardianAccountIds(), ...$reports->completerIds()];
    }

    public function countsOf(HomeworkTask $task): AudienceCounts
    {
        return AudienceCounts::of($this->audienceOf($task), $this->accounts);
    }

    /**
     * @return list<AudienceRow>
     */
    public function rowsOf(HomeworkTask $task): array
    {
        $audience = $this->audienceOf($task);

        $rowOf = fn (Person $person): AudienceRow => new AudienceRow(
            PersonReference::of($person),
            inStage: $audience->isMember($person),
            report: ReportView::of($audience->reportOf($person), $this->accounts, $person),
            parentAccounts: $this->accounts->parentsOf($person),
            ownAccount: $this->accounts->hasOwnAccount($person),
        );

        return new ArrayCollection([...$audience->members, ...$audience->leavers])->map($rowOf)->getValues();
    }

    private function audienceOf(HomeworkTask $task): TaskAudience
    {
        $key = $this->keys->get($task->stageId()->toRfc4122());
        $members = $key === null ? [] : $this->rosters->activeMembersOf($key);

        return TaskAudience::of($members, $this->reports->ofTask($task), $this->rosters);
    }
}
