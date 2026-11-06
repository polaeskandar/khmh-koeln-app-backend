<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Library\Time\Model\LocalDate;

final readonly class MembershipEndings
{
    public function end(Membership $membership, LocalDate $today): MembershipEnding
    {
        $ended = $membership->tenure()->endedFrom($today);

        if ($ended === null) {
            return MembershipEnding::DeleteInstead;
        }

        $membership->changeTenure($ended);

        return MembershipEnding::Ended;
    }
}
