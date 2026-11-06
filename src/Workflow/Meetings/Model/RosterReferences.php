<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class RosterReferences
{
    /**
     * @param ReadableCollection<non-empty-string, Service> $services
     * @param ReadableCollection<non-empty-string, Stage> $stages
     */
    private function __construct(
        private ReadableCollection $services,
        private ReadableCollection $stages,
    ) {
    }

    /**
     * @param ReadableCollection<int, Service> $services
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function of(ReadableCollection $services, ReadableCollection $stages): self
    {
        return new self(
            Collections::firstPerKey($services, keyOf: self::serviceKey(...)),
            Collections::firstPerKey($stages, keyOf: self::stageKey(...)),
        );
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return list<Uuid>
     */
    public static function serviceIdsOf(ReadableCollection $schedules): array
    {
        return $schedules->map(static fn (Schedule $schedule): Uuid => $schedule->roster()->serviceId)->getValues();
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     *
     * @return list<Uuid>
     */
    public static function stageIdsOf(ReadableCollection $schedules): array
    {
        return Collections::mapPresent(
            $schedules,
            static fn (Schedule $schedule): ?Uuid => $schedule->roster()->stageId,
        )->getValues();
    }

    public function serviceOf(Schedule $schedule): ?ServiceReference
    {
        $service = $this->services->get($schedule->roster()->serviceId->toRfc4122());

        return $service === null ? null : ServiceReference::of($service);
    }

    public function stageOf(Schedule $schedule): ?StageReference
    {
        $stageId = $schedule->roster()->stageId?->toRfc4122();
        $stage = $stageId === null ? null : $this->stages->get($stageId);

        return $stage === null ? null : StageReference::of($stage);
    }

    /**
     * @return non-empty-string
     */
    private static function serviceKey(Service $service): string
    {
        return $service->id()->toRfc4122();
    }

    /**
     * @return non-empty-string
     */
    private static function stageKey(Stage $stage): string
    {
        return $stage->id()->toRfc4122();
    }
}
