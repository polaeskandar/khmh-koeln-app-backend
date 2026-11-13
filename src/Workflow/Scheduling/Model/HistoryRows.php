<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Orderings;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class HistoryRows
{
    private const string COLLATION = 'de';

    /**
     * @param ReadableCollection<non-empty-string, Person> $members
     * @param list<SessionTally> $tallies
     *
     * @return list<HistoryRow>
     */
    public static function of(ReadableCollection $members, array $tallies): array
    {
        $collator = new Collator(self::COLLATION);

        $ordered = Orderings::byKeys($members, keysOf: static fn (Person $person): array => [
            (string) $collator->getSortKey($person->name()->lastName),
            (string) $collator->getSortKey($person->name()->firstName),
            $person->id()->toRfc4122(),
        ]);

        return new ArrayCollection($ordered)->map(
            static fn (Person $person): HistoryRow => HistoryRow::of($person, $tallies),
        )->getValues();
    }
}
