<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\Entity\Attendance;
use App\Context\Scheduling\Domain\Query\AttendanceQueryInterface;
use Symfony\Component\Uid\Uuid;

interface AttendanceRepositoryInterface extends AttendanceQueryInterface
{
    public function save(Attendance $attendance): void;

    public function currentMark(Uuid $sessionId, Uuid $personId): ?Attendance;
}
