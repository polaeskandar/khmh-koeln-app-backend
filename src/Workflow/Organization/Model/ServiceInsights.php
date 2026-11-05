<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use App\Workflow\Attendance\Model\SessionTally;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceInsights
{
    /**
     * @param ReadableCollection<int, Service> $services
     * @param ReadableCollection<non-empty-string, PlannedDate> $next
     * @param ReadableCollection<int, Session> $completed
     * @param ReadableCollection<non-empty-string, SessionTally> $tallies
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<ServiceInsight>
     */
    public static function of(
        ReadableCollection $services,
        ReadableCollection $next,
        ReadableCollection $completed,
        ReadableCollection $tallies,
        ReadableCollection $stages,
    ): array {
        return $services->map(static function (Service $service) use (
            $next,
            $completed,
            $tallies,
            $stages,
        ): ServiceInsight {
            $serviceId = $service->id()->toRfc4122();

            return new ServiceInsight(
                $serviceId,
                self::nextOf($next, $serviceId, $stages),
                AttendanceTrend::of(
                    $completed->filter(
                        static fn (Session $session): bool => self::serviceIdOf($session) === $serviceId,
                    ),
                    $tallies,
                ),
            );
        })->getValues();
    }

    /**
     * @param ReadableCollection<non-empty-string, PlannedDate> $next
     *
     * @return list<Uuid>
     */
    public static function stageIdsOf(ReadableCollection $next): array
    {
        return Collections::mapPresent(
            $next,
            static fn (PlannedDate $date): ?Uuid => $date->schedule->roster()->stageId,
        )->getValues();
    }

    /**
     * @param ReadableCollection<non-empty-string, PlannedDate> $next
     * @param ReadableCollection<int, Stage> $stages
     */
    private static function nextOf(
        ReadableCollection $next,
        string $serviceId,
        ReadableCollection $stages,
    ): ?NextMeeting {
        $date = $next->filter(static fn (PlannedDate $planned): bool => self::isOf($planned, $serviceId))->first();

        return $date instanceof PlannedDate ? NextMeeting::of($date, $stages) : null;
    }

    private static function isOf(PlannedDate $planned, string $serviceId): bool
    {
        return $planned->schedule->roster()->serviceId->toRfc4122() === $serviceId;
    }

    private static function serviceIdOf(Session $session): string
    {
        return $session->schedule()->roster()->serviceId->toRfc4122();
    }
}
