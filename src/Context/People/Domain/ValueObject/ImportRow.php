<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

final readonly class ImportRow
{
    public function __construct(
        public int $line,
        public PersonName $name,
        public Placement $placement,
        public string $where,
    ) {
    }

    public function rosterKey(): string
    {
        $stage = $this->placement->stageId?->toRfc4122() ?? '-';

        return $this->name->key . ' ' . $this->placement->serviceId->toRfc4122() . ' ' . $stage;
    }

    public function line(string $reason): ImportLine
    {
        return new ImportLine($this->line, $this->name->fullName() . ' ' . $reason . ' ' . $this->where);
    }
}
