<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Model\LocalDate;

final readonly class MembershipMove
{
    public static function changesNothing(Membership $membership, Placement $target): bool
    {
        if ($membership->tenure()->endsOn !== null) {
            throw new RuleViolation('people.membershipEnded');
        }

        return $target->stageId?->toRfc4122() === $membership->stageId()?->toRfc4122();
    }

    public static function applied(Membership $membership, Placement $target, LocalDate $today): Membership
    {
        $ended = $membership->tenure()->endedFrom($today);

        if ($ended === null) {
            $membership->changeStage($target->stageId);

            return $membership;
        }

        $membership->changeTenure($ended);

        return new Membership($membership->person(), $membership->serviceId(), $target->stageId, Tenure::from($today));
    }
}
