<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Library\Problem\Exception\NotFound;

final readonly class RecurringSchedule
{
    public static function is(Schedule $schedule): bool
    {
        return !$schedule->isOneOff();
    }

    public static function required(?Schedule $schedule): Schedule
    {
        return $schedule !== null && self::is($schedule) ? $schedule : throw NotFound::of('schedule');
    }
}
