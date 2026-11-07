<?php

declare(strict_types=1);

namespace App\Interface\Api\Attendance\Response;

use App\Workflow\Attendance\Model\Correction;
use JsonSerializable;
use Override;

final readonly class AttendanceCorrectionsResponse implements JsonSerializable
{
    /**
     * @param list<Correction> $corrections
     */
    public function __construct(
        private array $corrections,
    ) {
    }

    /**
     * @return array{items: list<Correction>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['items' => $this->corrections];
    }
}
