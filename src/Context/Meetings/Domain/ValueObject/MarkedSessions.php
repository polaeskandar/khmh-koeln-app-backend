<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
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
