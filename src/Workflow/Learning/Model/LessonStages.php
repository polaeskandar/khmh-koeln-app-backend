<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\LessonStage;
use App\Context\Organization\Domain\Entity\Stage;

final readonly class LessonStages
{
    public static function opened(Stage $stage): LessonStage
    {
        $service = $stage->service();

        $archivedName = match (true) {
            $service->isArchived() => $service->name()->value,
            $stage->isArchived() => $stage->name()->value,
            default => null,
        };

        return LessonStage::open($stage->id(), $archivedName);
    }

    public static function isOpen(Stage $stage): bool
    {
        return !$stage->isArchived() && !$stage->service()->isArchived();
    }
}
