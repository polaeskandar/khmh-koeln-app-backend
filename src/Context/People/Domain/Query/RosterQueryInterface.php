<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Query;

use App\Library\Time\Model\LocalDate;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

interface RosterQueryInterface
{
    /**
     * @param list<Uuid> $serviceIds
     *
     * @return ReadableCollection<string, array{members: int, serviceLevel: int}>
     */
    public function countServiceMembers(array $serviceIds, LocalDate $on): ReadableCollection;

    /**
     * @param list<Uuid> $stageIds
     *
     * @return ReadableCollection<string, int>
     */
    public function countStageMembers(array $stageIds, LocalDate $on): ReadableCollection;
}
