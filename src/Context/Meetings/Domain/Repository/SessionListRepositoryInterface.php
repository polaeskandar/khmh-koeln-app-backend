<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Session;
use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface SessionListRepositoryInterface
{
    /**
     * @return ReadableCollection<int, Session>
     */
    public function on(LocalDate $date): ReadableCollection;

    /**
     * @return ReadableCollection<int, Session>
     */
    public function openBetween(LocalDate $from, LocalDate $to): ReadableCollection;

    /**
     * @param list<Uuid> $excludedServiceIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function latestClosed(int $limit, array $excludedServiceIds): ReadableCollection;

    /**
     * @param list<Uuid> $ids
     *
     * @return ReadableCollection<int, Session>
     */
    public function byIds(array $ids): ReadableCollection;
}
