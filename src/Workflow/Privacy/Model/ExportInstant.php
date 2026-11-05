<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Library\Time\Client\Calendar;
use DateTimeInterface;

final readonly class ExportInstant
{
    public static function of(?DateTimeInterface $instant, Calendar $calendar): ?string
    {
        return $instant === null ? null : $calendar->formatInstant($instant);
    }
}
