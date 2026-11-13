<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\CoveredRosters;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface SessionListRepositoryInterface
{
    /**
     * @return ReadableCollection<int, Session>
     */
    public function listOn(LocalDate $date): ReadableCollection;

    /**
     * @return ReadableCollection<int, Session>
     */
    public function openBetween(LocalDate $from, LocalDate $to): ReadableCollection;

    /**
     * @param list<Uuid> $archivedIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function latestClosed(int $limit, array $archivedIds, CoveredRosters $covered): ReadableCollection;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Session>
     */
    public function byIds(array $ids): ReadableCollection;
}
