<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\Validation\ArchiveRule;
use App\Context\Organization\Domain\ValueObject\StageLineup;
use App\Library\Problem\Exception\NotFound;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class StageLifecycle
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
        private StageOrder $order,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Stage): void $pauseSchedules
     */
    public function archive(Uuid $id, callable $pauseSchedules): Stage
    {
        return $this->whileStructureLocked($id, function (Stage $stage) use ($pauseSchedules): Stage {
            ArchiveRule::ensureStageOpen($stage);
            $pauseSchedules($stage);
            $stage->archive($this->calendar->now());

            return $this->placed($stage);
        });
    }

    public function restore(Uuid $id): Stage
    {
        return $this->whileStructureLocked($id, function (Stage $stage): Stage {
            ArchiveRule::ensureStageArchived($stage);
            $stage->restore();

            return $this->placed($stage);
        });
    }

    /**
     * @param callable(Stage): Stage $work
     */
    private function whileStructureLocked(Uuid $stageId, callable $work): Stage
    {
        $serviceId = $this->stages->serviceIdOf($stageId) ?? throw NotFound::of('stage');

        return $this->services->withWriteLock($serviceId, function (Service $service) use ($stageId, $work): Stage {
            ArchiveRule::ensureStagesChangeable($service);

            return $work($this->stages->find($stageId) ?? throw NotFound::of('stage'));
        });
    }

    private function placed(Stage $stage): Stage
    {
        $lineup = StageLineup::besides($stage, $this->stages->ofService($stage->service()));
        $this->order->renumber($lineup->placing($stage));

        return $stage;
    }
}
