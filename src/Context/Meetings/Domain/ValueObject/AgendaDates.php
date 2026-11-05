<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AgendaDates
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     * @param ReadableCollection<int, Session> $sessions
     * @param list<PlannedDate> $planned
     */
    public function __construct(
        public ReadableCollection $schedules,
        public ReadableCollection $sessions,
        public array $planned,
    ) {
    }
}
