<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Repository\GuardianRelationshipRepositoryInterface;
use App\Context\People\Domain\Repository\PersonRepositoryInterface;
use App\Context\People\Domain\Repository\RosterRepositoryInterface;
use App\Context\People\Domain\ValueObject\RosterBook;
use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class StageRosters
{
    public function __construct(
        private RosterRepositoryInterface $rosters,
        private PersonRepositoryInterface $people,
        private GuardianRelationshipRepositoryInterface $guardians,
    ) {
    }

    /**
     * @param list<RosterKey> $keys
     * @param list<Uuid> $alsoPersonIds
     */
    public function getRosterBookOn(array $keys, array $alsoPersonIds): RosterBook
    {
        $rosters = $this->rosters->rostersOn($keys);
        $ids = [...self::idsIn($rosters), ...$alsoPersonIds];

        return new RosterBook(
            $rosters,
            Collections::firstPerKey($this->people->findByIds($ids), keyOf: self::personKey(...)),
            $this->guardians->approvedForChildren($ids),
        );
    }

    /**
     * @param ReadableCollection<string, list<Uuid>> $rosters
     *
     * @return list<Uuid>
     */
    private static function idsIn(ReadableCollection $rosters): array
    {
        return $rosters->reduce(
            /**
             * @param list<Uuid> $all
             * @param list<Uuid> $ids
             *
             * @return list<Uuid>
             */
            static fn (array $all, array $ids): array => [...$all, ...$ids],
            initial: [],
        );
    }

    private static function personKey(Person $person): string
    {
        return $person->id()->toRfc4122();
    }
}
