<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\Enum\SessionStatus;
use App\Library\Time\Client\Calendar;
use App\Workflow\Meetings\Model\SessionView;
use Doctrine\Common\Collections\ReadableCollection;
use LogicException;

final readonly class OverviewCard
{
    public function __construct(
        public BoardSession $session,
        public AttendanceCounts $counts,
        public bool $active,
    ) {
    }

    /**
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public static function of(
        Session $session,
        SessionView $view,
        ReadableCollection $tallies,
        AccountNames $closers,
        Calendar $calendar,
    ): self {
        $tally = $tallies->get($session->id()->toRfc4122()) ?? throw new LogicException('Every session has a tally.');

        return new self(
            BoardSession::of($session, $view, $closers, $calendar),
            AttendanceCounts::of($tally->counts),
            active: $session->progress()->status === SessionStatus::Active,
        );
    }
}
