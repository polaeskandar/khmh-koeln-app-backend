<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Context\Organization\Domain\ValueObject\Archival;
use App\Context\Organization\Domain\ValueObject\StageLineup;
use App\Library\Problem\Exception\NotFound;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class StageArchives
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
        private StageOrder $order,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Stage): bool $pause
     */
    public function archive(string $id, callable $pause): Stage
    {
        return $this->whileStructureLocked($id, function (Stage $stage) use ($pause): Stage {
            self::archivalOf($stage)->ensureOpen('stages.alreadyArchived');

            if (!$pause($stage)) {
                throw new RuleViolation('stages.takingAttendance', ['name' => $stage->name()->value]);
            }

            $stage->archive($this->calendar->now());

            return $this->placed($stage);
        });
    }

    public function restore(string $id): Stage
    {
        return $this->whileStructureLocked($id, function (Stage $stage): Stage {
            self::archivalOf($stage)->ensureArchived('stages.notArchived');
            $stage->restore();

            return $this->placed($stage);
        });
    }

    /**
     * @param callable(Stage): Stage $work
     */
    private function whileStructureLocked(string $id, callable $work): Stage
    {
        $stageId = Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('stage');
        $serviceId = $this->stages->serviceIdOf($stageId) ?? throw NotFound::of('stage');

        return $this->services->withStructureLock($serviceId, function (Service $service) use ($stageId, $work): Stage {
            StageChecks::ensureOpen($service);

            return $work($this->stages->find($stageId) ?? throw NotFound::of('stage'));
        });
    }

    private function placed(Stage $stage): Stage
    {
        $lineup = StageLineup::besides($stage, $this->stages->ofService($stage->service()));
        $this->order->renumber($lineup->placing($stage));

        return $stage;
    }

    private static function archivalOf(Stage $stage): Archival
    {
        return new Archival($stage->archivedAt(), $stage->name()->value);
    }
}
