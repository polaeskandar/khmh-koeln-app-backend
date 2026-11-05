<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Domain\Entity\User;
use App\Context\Learning\Domain\Entity\HomeworkAssignment;
use App\Context\Learning\Domain\ValueObject\TaskReports;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\StageRosters;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\People\Model\PersonReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class TaskAudiences
{
    /**
     * @param ReadableCollection<string, RosterKey> $keys
     * @param ReadableCollection<string, User> $accounts
     */
    private function __construct(
        private TaskReports $reports,
        private ReadableCollection $keys,
        private RosterBook $rosters,
        private ReadableCollection $accounts,
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
        $accountIds = [...$book->guardianAccountIds(), ...$reports->completerIds()];

        return new self(
            $reports,
            $keys,
            $book,
            Collections::firstPerKey($accounts->accounts($accountIds), keyOf: self::accountKey(...)),
        );
    }

    public function countsOf(HomeworkAssignment $task): AudienceCounts
    {
        $audience = $this->audienceOf($task);
        $members = new ArrayCollection($audience->members);
        $reported = $members->filter(static fn (Person $person): bool => $audience->reportOf($person) !== null);

        $unreachable = $members->filter(
            fn (Person $person): bool => $audience->reportOf($person) === null && $this->parentsOf($person) === [],
        );

        return new AudienceCounts(
            assigned: $members->count(),
            completed: $reported->count(),
            completedAfterDueDate: $reported
                ->filter(static fn (Person $person): bool => $audience->reportOf($person)?->late === true)
                ->count(),
            withoutParentAccount: $unreachable->count(),
            reportedTotal: $audience->reports->count(),
        );
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
            report: ReportView::of($audience->reportOf($person), $this->accounts),
            parentAccounts: $this->parentsOf($person),
        );

        return new ArrayCollection([...$audience->members, ...$audience->leavers])->map($rowOf)->getValues();
    }

    private function audienceOf(HomeworkAssignment $task): TaskAudience
    {
        $key = $this->keys->get($task->stageId()->toRfc4122());
        $members = $key === null ? [] : $this->rosters->activeMembersOf($key);

        return TaskAudience::of($members, $this->reports->of($task), $this->rosters);
    }

    /**
     * @return list<AccountReference>
     */
    private function parentsOf(Person $child): array
    {
        $accountOf = fn (Uuid $id): ?User => $this->accounts->get($id->toRfc4122());

        return Collections::mapPresent(new ArrayCollection($this->rosters->guardianAccountIdsOf($child)), $accountOf)
            ->filter(static fn (User $user): bool => $user->status()->isActive())
            ->map(AccountReference::of(...))
            ->getValues();
    }

    private static function accountKey(User $user): string
    {
        return $user->id()->toRfc4122();
    }
}
