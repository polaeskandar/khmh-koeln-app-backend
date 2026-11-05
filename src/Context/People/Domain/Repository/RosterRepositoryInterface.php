<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\ValueObject\RosterKey;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RosterRepositoryInterface
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

    /**
     * @param list<Uuid> $serviceIds
     *
     * @return ReadableCollection<string, array{members: int, serviceLevel: int}>
     */
    public function serviceMemberCounts(array $serviceIds, LocalDate $on): ReadableCollection;

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, int>
     */
    public function stageMemberCounts(array $stageIds, LocalDate $on): ReadableCollection;
}
