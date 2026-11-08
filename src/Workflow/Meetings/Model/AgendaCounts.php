<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Session;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AgendaCounts
{
    /**
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    public static function of(ReadableCollection $tallies, Session $session): ?AttendanceCounts
    {
        $tally = $tallies->get($session->id()->toRfc4122());

        return $tally === null ? null : AttendanceCounts::of($tally->counts);
    }
}
