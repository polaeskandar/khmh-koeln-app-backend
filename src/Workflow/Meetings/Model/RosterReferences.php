<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\Entity\Schedule;
use App\Context\Meetings\Domain\Entity\Session;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageSummary;
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
     * @param ReadableCollection<int, Session> $sessions
     */
    public static function ofSessions(StructureLookup $structure, ReadableCollection $sessions): self
    {
        return self::lookedUpIn($structure, $sessions->map(
            static fn (Session $session): Schedule => $session->schedule(),
        ));
    }

    /**
     * @param ReadableCollection<int, Schedule> $schedules
     */
    public static function lookedUpIn(StructureLookup $structure, ReadableCollection $schedules): self
    {
        $serviceIds = $schedules->map(static fn (Schedule $schedule): Uuid => $schedule->roster()->serviceId);

        $stageIds = Collections::mapPresent(
            $schedules,
            static fn (Schedule $schedule): ?Uuid => $schedule->roster()->stageId,
        );

        return new self(
            Collections::firstPerKey($structure->services($serviceIds->getValues()), keyOf: self::serviceKey(...)),
            Collections::firstPerKey($structure->stages($stageIds->getValues()), keyOf: self::stageKey(...)),
        );
    }

    public function serviceOf(Schedule $schedule): ?ServiceReference
    {
        $service = $this->services->get($schedule->roster()->serviceId->toRfc4122());

        return $service === null ? null : ServiceReference::of($service);
    }

    public function stageOf(Schedule $schedule): ?StageSummary
    {
        $stageId = $schedule->roster()->stageId?->toRfc4122();
        $stage = $stageId === null ? null : $this->stages->get($stageId);

        return $stage === null ? null : StageSummary::of($stage);
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
