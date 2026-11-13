<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Context\Scheduling\Domain\Entity\Schedule;
use App\Context\Scheduling\Domain\Entity\Session;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class MarkedSessions
{
    /**
     * @param ReadableCollection<int, Session> $sessions
     * @param ReadableCollection<int, Schedule> $schedules
     * @param ReadableCollection<string, list<string>> $present
     */
    public function __construct(
        public ReadableCollection $sessions,
        public ReadableCollection $schedules,
        public ReadableCollection $present,
    ) {
    }
}
