<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Query\PersonQueryInterface;
use App\Library\Collections\Model\Collections;
use App\Platform\Directory\Contract\PersonNames;
use Override;
use Symfony\Component\Uid\Uuid;

final readonly class DirectoryNames implements PersonNames
{
    public function __construct(
        private PersonQueryInterface $people,
    ) {
    }

    /**
     * @param list<Uuid> $personIds
     *
     * @return array<string, string>
     */
    #[Override]
    public function namesOf(array $personIds): array
    {
        $people = Collections::firstPerKey(
            $this->people->findByIds($personIds),
            keyOf: static fn (Person $person): string => $person->id()->toRfc4122(),
        );

        return $people->map(
            static fn (Person $person): string => $person->name()->displayName ?? $person->name()->fullName(),
        )->toArray();
    }
}
