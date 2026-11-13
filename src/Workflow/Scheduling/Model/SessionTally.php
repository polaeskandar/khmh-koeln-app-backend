<?php

declare(strict_types=1);

namespace App\Workflow\Scheduling\Model;

use App\Context\Scheduling\Domain\ValueObject\SessionCounts;

final readonly class SessionTally
{
    /**
     * @param list<string> $rosterIds
     * @param list<string> $presentIds
     */
    public function __construct(
        public array $rosterIds,
        public array $presentIds,
        public SessionCounts $counts,
    ) {
    }
}
