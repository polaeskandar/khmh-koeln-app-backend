<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Platform\Directory\Contract\PersonNames;
use Closure;
use Collator;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class AccountNames
{
    private const string COLLATION = 'de';

    public function __construct(
        private PersonNames $people,
    ) {
    }

    /**
     * @param list<array{personId: string, ...}> $rows
     *
     * @return array<string, string> the name of each row's person, by person id
     */
    public function of(array $rows): array
    {
        return $this->people->namesOf(
            new ArrayCollection($rows)->map(static fn (array $row): Uuid => Uuid::fromString(
                $row['personId'],
            ))->getValues(),
        );
    }

    /**
     * @return Closure(string): string
     */
    public static function sortKeys(): Closure
    {
        $collator = new Collator(self::COLLATION);

        return static fn (string $name): string => (string) $collator->getSortKey($name);
    }
}
