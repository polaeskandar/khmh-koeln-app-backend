<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Library\Collections\Model\Collections;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class AlphabeticalRows
{
    private const string COLLATION = 'de';

    private const string FIELD_SEPARATOR = "\x00";

    /**
     * @param list<AudienceRow> $rows
     *
     * @return list<AudienceRow>
     */
    public static function of(array $rows): array
    {
        $collator = new Collator(self::COLLATION);

        return Collections::sortedBy(
            new ArrayCollection($rows),
            keyOf: static fn (AudienceRow $row): string => self::keyOf($collator, $row),
        );
    }

    private static function keyOf(Collator $collator, AudienceRow $row): string
    {
        $person = $row->person;
        $lastName = (string) $collator->getSortKey($person->lastName);
        $firstName = (string) $collator->getSortKey($person->firstName);

        return $lastName . self::FIELD_SEPARATOR . $firstName . self::FIELD_SEPARATOR . $person->id;
    }
}
