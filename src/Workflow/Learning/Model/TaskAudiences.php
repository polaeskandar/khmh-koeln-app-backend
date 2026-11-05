<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\StageRosters;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

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

    public static function lookedUpIn(
        StructureLookup $structure,
        StageRosters $rosters,
        AccountLookup $accounts,
        TaskReports $reports,
    ): self {
        $keys = AudienceKeys::of($structure, $reports);
        $book = $rosters->on($keys->getValues(), $reports->reporterIds());

        return new self($reports, $keys, $book, AudienceAccounts::lookedUpIn($accounts, $book, $reports));
    }

    public function reports(): TaskReports
    {
        return $this->reports;
    }

    public function countsOf(HomeworkAssignment $task): AudienceCounts
    {
        return AudienceCounts::of($this->audienceOf($task), $this->accounts);
    }

    /**
     * @return list<AudienceRow>
     */
    public function rowsOf(HomeworkAssignment $task): array
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

    private function audienceOf(HomeworkAssignment $task): TaskAudience
    {
        $key = $this->keys->get($task->stageId()->toRfc4122());
        $members = $key === null ? [] : $this->rosters->activeMembersOf($key);

        return TaskAudience::of($members, $this->reports->of($task), $this->rosters);
    }
}
