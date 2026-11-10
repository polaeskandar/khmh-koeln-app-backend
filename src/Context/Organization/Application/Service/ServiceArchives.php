<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Validation\ArchiveRule;
use App\Library\Problem\Exception\NotFound;
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
        return $this->services->withStructureLock(self::idOf($id), function (Service $service) use ($pause): Service {
            ArchiveRule::ensureServiceOpen($service);

            if (!$pause($service)) {
                throw new RuleViolation('services.takingAttendance', ['name' => $service->name()->value]);
            }

            $service->archive($this->calendar->now());
            $this->services->save($service);

            return $service;
        });
    }

    public function restore(string $id): Service
    {
        return $this->services->withStructureLock(self::idOf($id), function (Service $service): Service {
            ArchiveRule::ensureServiceArchived($service);
            $service->restore();
            $this->services->save($service);

            return $service;
        });
    }

    private static function idOf(string $id): Uuid
    {
        return Uuid::isValid($id) ? Uuid::fromString($id) : throw NotFound::of('service');
    }
}
