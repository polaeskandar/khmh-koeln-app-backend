<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Validation\ArchiveRule;
use App\Library\Time\Client\Calendar;
use Symfony\Component\Uid\Uuid;

final readonly class ServiceLifecycle
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private Calendar $calendar,
    ) {
    }

    /**
     * @param callable(Service): void $pauseSchedules
     */
    public function archive(Uuid $id, callable $pauseSchedules): Service
    {
        return $this->services->withWriteLock($id, function (Service $service) use ($pauseSchedules): Service {
            ArchiveRule::ensureServiceOpen($service);
            $pauseSchedules($service);
            $service->archive($this->calendar->now());
            $this->services->save($service);

            return $service;
        });
    }

    public function restore(Uuid $id): Service
    {
        return $this->services->withWriteLock($id, function (Service $service): Service {
            ArchiveRule::ensureServiceArchived($service);
            $service->restore();
            $this->services->save($service);

            return $service;
        });
    }
}
