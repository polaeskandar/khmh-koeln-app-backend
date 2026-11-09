<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\DuplicatePolicy;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\ValueObject\ImportLine;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Context\People\Domain\ValueObject\ImportRow;
use App\Context\People\Domain\ValueObject\Tenure;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Library\Time\Client\Calendar;
use App\Platform\Doctrine\Contract\TransactionsInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MemberImports
{
    public function __construct(
        private PersonRepositoryInterface $people,
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
        private TransactionsInterface $transactions,
    ) {
    }

    /**
     * @param list<ImportRow> $rows
     */
    public function plan(array $rows, DuplicatePolicy $duplicates): ImportPlan
    {
        $all = new ArrayCollection($rows);
        $unique = new ArrayCollection(Collections::firstPerKey($all, keyOf: self::rosterKey(...))->getValues());
        $repeated = $all->filter(static fn (ImportRow $row): bool => !$unique->contains($row));
        $rostered = $unique->filter($this->isOnRoster(...));
        $open = $unique->filter(static fn (ImportRow $row): bool => !$rostered->contains($row));

        $namesakes = $duplicates === DuplicatePolicy::Skip
            ? $open->filter($this->hasNamesake(...))
            : new ArrayCollection();

        $created = $open->filter(static fn (ImportRow $row): bool => !$namesakes->contains($row));

        return new ImportPlan(
            [],
            $created->map(static fn (ImportRow $row): ImportLine => $row->line('→'))->getValues(),
            self::skipped($repeated, $rostered, $namesakes),
            $this->peopleOf($created->getValues()),
        );
    }

    public function save(ImportPlan $plan): void
    {
        $this->transactions->run(function () use ($plan): void {
            foreach ($plan->people as [$person, $memberships]) {
                $this->people->save($person, ...$memberships);
            }
        });
    }

    /**
     * @param list<ImportRow> $rows
     *
     * @return list<array{Person, list<Membership>}>
     */
    private function peopleOf(array $rows): array
    {
        $today = $this->calendar->today();
        $all = new ArrayCollection($rows);

        return Collections::firstPerKey($all, keyOf: self::nameKey(...))->map(static function (ImportRow $first) use (
            $all,
            $today,
        ): array {
            $person = new Person($first->name);
            $same = $all->filter(static fn (ImportRow $row): bool => $row->name->key === $first->name->key);

            return [
                $person,
                $same->map(
                    static fn (ImportRow $row): Membership => new Membership(
                        $person,
                        $row->placement->serviceId,
                        $row->placement->stageId,
                        Tenure::from($today),
                    ),
                )->getValues(),
            ];
        })->getValues();
    }

    private function isOnRoster(ImportRow $row): bool
    {
        $today = $this->calendar->today();

        return !$this->people
            ->withNameKey($row->name->key)
            ->filter(fn (Person $person): bool => $this->memberships->hasCurrent(
                $person,
                $row->placement->serviceId,
                $row->placement->stageId,
                $today,
            ))
            ->isEmpty();
    }

    private function hasNamesake(ImportRow $row): bool
    {
        return !$this->people->withNameKey($row->name->key)->isEmpty();
    }

    /**
     * @param ReadableCollection<int, ImportRow> $repeated
     * @param ReadableCollection<int, ImportRow> $rostered
     * @param ReadableCollection<int, ImportRow> $namesakes
     *
     * @return list<ImportLine>
     */
    private static function skipped(
        ReadableCollection $repeated,
        ReadableCollection $rostered,
        ReadableCollection $namesakes,
    ): array {
        $skipped = [
            ...$repeated->map(static fn (ImportRow $row): ImportLine => $row->line('is listed twice for'))->getValues(),
            ...$rostered->map(static fn (ImportRow $row): ImportLine => $row->line('is already on'))->getValues(),
            ...$namesakes->map(
                static fn (ImportRow $row): ImportLine => $row->line('has a namesake, not added to'),
            )->getValues(),
        ];

        return Collections::sortedBy(new ArrayCollection($skipped), keyOf: self::lineKey(...));
    }

    private static function rosterKey(ImportRow $row): string
    {
        return $row->rosterKey();
    }

    private static function nameKey(ImportRow $row): string
    {
        return 'name ' . $row->name->key;
    }

    private static function lineKey(ImportLine $line): string
    {
        return Text::format('%08d', $line->line)->toString();
    }
}
