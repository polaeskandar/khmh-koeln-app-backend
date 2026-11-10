<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Query\RosterQueryInterface;
use App\Context\People\Domain\ValueObject\RosterKey;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RosterRepositoryInterface extends RosterQueryInterface
{
    /**
     * @return list<Uuid>
     */
    public function rosterOn(RosterKey $key): array;

    /**
     * @param list<RosterKey> $keys
     *
     * @return ReadableCollection<string, list<Uuid>>
     */
    public function rostersOn(array $keys): ReadableCollection;
}
