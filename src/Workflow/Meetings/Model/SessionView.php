<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Library\Time\Client\Calendar;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class SessionView
{
    public function __construct(
        public SessionDetails $details,
        public ScheduleRoster $roster,
        public SessionTimes $times,
        public SessionProgress $progress,
        public SessionAbilities $can,
    ) {
    }

    public static function of(
        Session $session,
        StructureLookup $structure,
        Calendar $calendar,
        SessionAbilities $can,
    ): self {
        $schedule = $session->schedule();

        return new self(
            SessionDetails::of($session),
            ScheduleRoster::of($schedule, RosterReferences::lookedUpIn($structure, new ArrayCollection([$schedule]))),
            SessionTimes::of($session, $calendar),
            SessionProgress::of($session, $can),
            $can,
        );
    }
}
