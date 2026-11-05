<?php

declare(strict_types=1);

namespace App\Workflow\Attendance\Model;

use App\Context\Attendance\Domain\ValueObject\SessionCounts;

final readonly class SessionTally
{
    /**
     * @param list<non-empty-string> $rosterIds
     * @param list<non-empty-string> $presentIds
     */
    public function __construct(
        public array $rosterIds,
        public array $presentIds,
        public SessionCounts $counts,
    ) {
    }
}
