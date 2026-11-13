<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Scheduling\Domain\ValueObject\PlannedDate;
use App\Context\Scheduling\Domain\ValueObject\StructureImpact;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceInsights
{
    /**
     * @param ReadableCollection<int, Service> $services
     * @param ReadableCollection<non-empty-string, PlannedDate> $next
     * @param ReadableCollection<int, Stage> $stages
     * @param ReadableCollection<string, StructureImpact> $impacts
     *
     * @return list<ServiceInsight>
     */
    public static function of(
        ReadableCollection $services,
        ReadableCollection $next,
        ReadableCollection $stages,
        RecentAttendance $recent,
        ReadableCollection $impacts,
    ): array {
        return $services->map(static function (Service $service) use (
            $next,
            $stages,
            $recent,
            $impacts,
        ): ServiceInsight {
            $serviceId = $service->id()->toRfc4122();

            return new ServiceInsight(
                $serviceId,
                self::nextOf($next, $serviceId, $stages),
                $recent->trendOf($serviceId),
                ArchiveImpact::of($impacts->get($serviceId)),
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
}
