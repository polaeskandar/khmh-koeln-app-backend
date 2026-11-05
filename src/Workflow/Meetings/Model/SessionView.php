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
        $references = RosterReferences::lookedUpIn($structure, new ArrayCollection([$session->schedule()]));

        return self::with($session, $references, $calendar, $can);
    }

    public static function with(
        Session $session,
        RosterReferences $references,
        Calendar $calendar,
        SessionAbilities $can,
    ): self {
        return new self(
            SessionDetails::of($session),
            ScheduleRoster::of($session->schedule(), $references),
            SessionTimes::of($session, $calendar),
            SessionProgress::of($session, $can),
            $can,
        );
    }
}
