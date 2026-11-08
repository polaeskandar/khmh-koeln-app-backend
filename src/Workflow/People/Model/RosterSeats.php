<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Meetings\Domain\ValueObject\Roster;
use App\Context\People\Domain\Entity\Membership;

final readonly class RosterSeats
{
    public static function meetsIn(Membership $membership, Roster $roster): bool
    {
        $stage = $roster->stageId === null || $roster->stageId->equals($membership->stageId());

        return $stage && $membership->serviceId()->equals($roster->serviceId);
    }

    public static function isOwnRoster(Membership $membership, Roster $roster): bool
    {
        $stage = $roster->stageId?->toRfc4122() === $membership->stageId()?->toRfc4122();

        return $stage && $membership->serviceId()->equals($roster->serviceId);
    }
}
