<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\Organization\Domain\Repository\StageRepositoryInterface;
use App\Library\Problem\Exception\InvalidInput;

final readonly class StageChoices
{
    private const string FIELD = 'placement.stageId';

    public function __construct(
        private StageRepositoryInterface $stages,
    ) {
    }

    public function of(?string $stageId, Service $service): ?Stage
    {
        if ($stageId === null) {
            return null;
        }

        $stage = $this->stages
            ->ofService($service)
            ->filter(static fn (Stage $stage): bool => $stage->id()->toRfc4122() === $stageId)
            ->first();

        if (!$stage instanceof Stage) {
            throw InvalidInput::field(self::FIELD, 'meetings.chooseStage');
        }

        if ($stage->isArchived()) {
            throw InvalidInput::field(self::FIELD, 'meetings.stageArchived', ['name' => $stage->name()->value]);
        }

        return $stage;
    }
}
