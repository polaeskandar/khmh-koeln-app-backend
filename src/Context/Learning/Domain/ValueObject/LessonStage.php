<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;
use Symfony\Component\Uid\Uuid;

final readonly class LessonStage
{
    private function __construct(
        public Uuid $id,
    ) {
    }

    public static function open(Uuid $id, ?string $archivedName): self
    {
        if ($archivedName !== null) {
            throw new RuleViolation('lessons.stageArchived', ['name' => $archivedName]);
        }

        return new self($id);
    }
}
