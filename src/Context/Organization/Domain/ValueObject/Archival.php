<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Library\Problem\Exception\RuleViolation;
use DateTimeImmutable;

final readonly class Archival
{
    public function __construct(
        private ?DateTimeImmutable $archivedAt,
        private string $name,
    ) {
    }

    public function ensureOpen(string $refusal): void
    {
        if ($this->archivedAt !== null) {
            throw new RuleViolation($refusal, ['name' => $this->name]);
        }
    }

    public function ensureArchived(string $refusal): void
    {
        if ($this->archivedAt === null) {
            throw new RuleViolation($refusal, ['name' => $this->name]);
        }
    }
}
