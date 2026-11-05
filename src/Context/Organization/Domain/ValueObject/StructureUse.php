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

    public function ensureUnused(string $kind, string $name): void
    {
        if ($this->sessions > 0) {
            throw new RuleViolation($kind . '.hasHistory', ['name' => $name, 'count' => $this->sessions]);
        }

        if ($this->lessons > 0) {
            throw new RuleViolation($kind . '.hasLessons', ['name' => $name, 'count' => $this->lessons]);
        }

        if ($this->members > 0) {
            throw new RuleViolation($kind . '.hasMembers', ['name' => $name, 'count' => $this->members]);
        }
    }
}
