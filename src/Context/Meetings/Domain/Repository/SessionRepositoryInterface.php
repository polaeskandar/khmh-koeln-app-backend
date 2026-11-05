<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;

interface SessionRepositoryInterface
{
    /**
     * @param list<LocalDate> $dates
     */
    public function createMissing(Schedule $schedule, array $dates, Actor $actor, DateTimeImmutable $at): int;
}
