<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\ErasedCounts;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface SessionTotalsRepositoryInterface
{
    /**
     * @param list<Schedule> $schedules
     * @param list<Uuid> $sessionIds
     *
     * @return ReadableCollection<int, Session>
     */
    public function ofSchedulesOrIds(array $schedules, array $sessionIds): ReadableCollection;

    public function addErased(Uuid $sessionId, ErasedCounts $share): void;
}
