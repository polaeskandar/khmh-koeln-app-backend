<?php

declare(strict_types=1);

namespace App\Context\People\Application\Service;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Enum\MembershipEnding;
use App\Context\People\Domain\Repository\MembershipRepositoryInterface;
use App\Library\Time\Client\Calendar;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class MembershipEndings
{
    public function __construct(
        private MembershipRepositoryInterface $memberships,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Membership): void $ensureAllowed
     */
    public function endById(Uuid $membershipId, callable $ensureAllowed): void
    {
        $today = $this->calendar->today();

        $this->memberships->withWriteLock($membershipId, function (Membership $membership) use (
            $today,
            $ensureAllowed,
        ): void {
            $ensureAllowed($membership);

            if (self::end($membership, $today) === MembershipEnding::DeleteInstead) {
                $this->memberships->remove($membership);

                return;
            }

            $this->memberships->save($membership);
        });
    }

    public static function end(Membership $membership, LocalDate $today): MembershipEnding
    {
        $ended = $membership->tenure()->endedFrom($today);

        if ($ended === null) {
            return MembershipEnding::DeleteInstead;
        }

        $membership->changeTenure($ended);

        return MembershipEnding::Ended;
    }
}
