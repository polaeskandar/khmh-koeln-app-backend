<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceArchives
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Service): bool $pause
     */
    public function archive(string $id, callable $pause): Service
    {
        return $this->services->withStructureLock(Uuid::fromString($id), function (Service $service) use (
            $pause,
        ): Service {
            $service->archival()->ensureOpen('services.alreadyArchived');

            if (!$pause($service)) {
                throw new RuleViolation('services.takingAttendance', ['name' => $service->name()->value]);
            }

            $service->archive($this->calendar->now()->toDateTimeImmutable());
            $this->services->save($service);

            return $service;
        });
    }

    public function restore(string $id): Service
    {
        return $this->services->withStructureLock(Uuid::fromString($id), function (Service $service): Service {
            $service->archival()->ensureArchived('services.notArchived');
            $service->restore();
            $this->services->save($service);

            return $service;
        });
    }
}
