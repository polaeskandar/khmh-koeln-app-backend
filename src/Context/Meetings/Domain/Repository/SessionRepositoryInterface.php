<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\Repository;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\ValueObject\Actor;
use App\Context\Meetings\Domain\ValueObject\GenerationResult;
use App\Library\Time\Model\LocalDate;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface SessionRepositoryInterface
{
    /**
     * @param callable(Schedule): list<LocalDate> $datesOf
     */
    public function createMissing(
        Uuid $scheduleId,
        callable $datesOf,
        Actor $actor,
        DateTimeImmutable $at,
    ): GenerationResult;

    public function reset(): void;
}
