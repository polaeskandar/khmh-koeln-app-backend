<?php

declare(strict_types=1);

namespace App\Context\Meetings\Application\Service;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Platform\Access\Model\Group;
use App\Platform\Access\Model\GroupScope;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class CoveredSchedules
{
    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return ReadableCollection<int, Schedule>
     */
    public static function within(GroupScope $scope, ReadableCollection $schedules): ReadableCollection
    {
        return $scope->filter($schedules, self::groupOf(...));
    }

    /**
     * @param ReadableCollection<int, Session> $sessions
     *
     * @return ReadableCollection<int, Session>
     */
    public static function sessionsWithin(GroupScope $scope, ReadableCollection $sessions): ReadableCollection
    {
        return $scope->filter($sessions, self::groupOfSession(...));
    }

    public static function groupOf(Schedule $schedule): Group
    {
        $roster = $schedule->roster();

        return new Group($roster->serviceId, $roster->stageId);
    }

    public static function groupOfSession(Session $session): Group
    {
        return self::groupOf($session->schedule());
    }
}
