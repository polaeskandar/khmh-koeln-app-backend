<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\Validation;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Problem\Exception\RuleViolation;

final readonly class ArchiveRule
{
    public static function ensureServiceOpen(Service $service): void
    {
        if ($service->isArchived()) {
            throw self::refusal('services.alreadyArchived', $service->name()->value);
        }
    }

    public static function ensureServiceArchived(Service $service): void
    {
        if (!$service->isArchived()) {
            throw self::refusal('services.notArchived', $service->name()->value);
        }
    }

    public static function ensureStagesChangeable(Service $service): void
    {
        if ($service->isArchived()) {
            throw self::refusal('services.archivedNoStageChanges', $service->name()->value);
        }
    }

    public static function ensureStageOpen(Stage $stage): void
    {
        if ($stage->isArchived()) {
            throw self::refusal('stages.alreadyArchived', $stage->name()->value);
        }
    }

    public static function ensureStageArchived(Stage $stage): void
    {
        if (!$stage->isArchived()) {
            throw self::refusal('stages.notArchived', $stage->name()->value);
        }
    }

    private static function refusal(string $code, string $name): RuleViolation
    {
        return new RuleViolation($code, ['name' => $name]);
    }
}
