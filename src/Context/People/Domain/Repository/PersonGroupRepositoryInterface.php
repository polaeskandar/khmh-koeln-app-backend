<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

interface PersonGroupRepositoryInterface
{
    /**
     * @return list<array{serviceId: string, stageId: ?string}> the rosters of the person's memberships that have not
     *     ended by the day
     */
    public function openOn(Uuid $personId, LocalDate $day): array;
}
