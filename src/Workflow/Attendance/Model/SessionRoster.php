<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Context\People\Domain\ValueObject\RosterKey;

final readonly class SessionRoster
{
    public static function isExpected(Session $session): bool
    {
        return $session->schedule()->roster()->mode === RosterMode::Expected;
    }

    public static function keyOf(Session $session): RosterKey
    {
        $roster = $session->schedule()->roster();

        return new RosterKey($roster->serviceId, $roster->stageId, $session->occurrenceDate());
    }
}
