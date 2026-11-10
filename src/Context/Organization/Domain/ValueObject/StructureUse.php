<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;

final readonly class StructureUse
{
    public function __construct(
        public int $sessions,
        public int $lessons,
        public int $members,
    ) {
    }

    public function ensureServiceUnused(string $name): void
    {
        $this->ensureUnused(
            $name,
            history: 'services.hasHistory',
            lessons: 'services.hasLessons',
            members: 'services.hasMembers',
        );
    }

    public function ensureStageUnused(string $name): void
    {
        $this->ensureUnused(
            $name,
            history: 'stages.hasHistory',
            lessons: 'stages.hasLessons',
            members: 'stages.hasMembers',
        );
    }

    private function ensureUnused(string $name, string $history, string $lessons, string $members): void
    {
        if ($this->sessions > 0) {
            throw new RuleViolation($history, ['name' => $name, 'count' => $this->sessions]);
        }

        if ($this->lessons > 0) {
            throw new RuleViolation($lessons, ['name' => $name, 'count' => $this->lessons]);
        }

        if ($this->members > 0) {
            throw new RuleViolation($members, ['name' => $name, 'count' => $this->members]);
        }
    }
}
