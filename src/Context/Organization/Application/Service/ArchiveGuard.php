<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Repository\ServiceRepositoryInterface;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class ArchiveGuard
{
    public function __construct(
        private ServiceRepositoryInterface $services,
        private StageRepositoryInterface $stages,
    ) {
    }

    public function ensureOpen(Uuid $serviceId, ?Uuid $stageId): void
    {
        $service = $this->services->find($serviceId);

        if ($service?->isArchived() === true) {
            throw new RuleViolation('meetings.serviceArchivedRestoreFirst', ['name' => $service->name()->value]);
        }

        $stage = $stageId === null ? null : $this->stages->find($stageId);

        if ($stage?->isArchived() === true) {
            throw new RuleViolation('meetings.stageArchivedRestoreFirst', ['name' => $stage->name()->value]);
        }
    }
}
