<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Attendance\Application\Service\AttendanceMarks;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Meetings\Application\Service\ServiceMeetings;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\ArchivedStructure;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Application\Service\Rosters;
use App\Workflow\Attendance\Model\SessionTallies;
use App\Workflow\Attendance\Model\SessionTally;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ServiceInsights
{
    private const string LEADER_ROLE = 'service_leader';

    /**
     * @param list<AccountReference> $leaders
     * @param ReadableCollection<non-empty-string, PlannedDate> $next
     * @param ReadableCollection<int, Session> $completed
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     */
    private function __construct(
        private array $leaders,
        private ReadableCollection $next,
        private ReadableCollection $completed,
        private ReadableCollection $tallies,
    ) {
    }

    public static function lookedUpIn(
        AccountLookup $accounts,
        ServiceMeetings $meetings,
        Rosters $rosters,
        AttendanceMarks $marks,
        ArchivedStructure $archived,
    ): self {
        $completed = $meetings->recentlyCompleted();

        return new self(
            self::leadersIn($accounts),
            $meetings->nextByService($archived),
            $completed,
            SessionTallies::onRosters($rosters, $marks, $completed),
        );
    }

    public static function ofNewService(AccountLookup $accounts): ServiceInsight
    {
        return new ServiceInsight(self::leadersIn($accounts), next: null, trend: AttendanceTrend::none());
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public function of(Service $service, ReadableCollection $stages): ServiceInsight
    {
        $next = $service->isArchived() ? null : $this->next->get($service->id()->toRfc4122());

        $own = $this->completed->filter(static fn (Session $session): bool => $session
            ->schedule()
            ->roster()
            ->serviceId->equals($service->id()));

        return new ServiceInsight(
            $this->leaders,
            $next === null ? null : NextMeeting::of($next, $stages),
            AttendanceTrend::of($own, $this->tallies),
        );
    }

    /**
     * @return list<AccountReference>
     */
    private static function leadersIn(AccountLookup $accounts): array
    {
        $leaders = new ArrayCollection($accounts->activeHoldersOf(self::LEADER_ROLE));

        return $leaders->map(AccountReference::of(...))->getValues();
    }
}
