<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Library\Collections\Model\Orderings;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AlphabeticalRows
{
    private const string COLLATION = 'de';

    /**
     * @param list<AudienceRow> $rows
     *
     * @return list<AudienceRow>
     */
    public static function of(array $rows): array
    {
        $collator = new Collator(self::COLLATION);

        return Orderings::byKeys(new ArrayCollection($rows), keysOf: static fn (AudienceRow $row): array => [
            (string) $collator->getSortKey($row->person->lastName),
            (string) $collator->getSortKey($row->person->firstName),
            $row->person->id,
        ]);
    }
}
