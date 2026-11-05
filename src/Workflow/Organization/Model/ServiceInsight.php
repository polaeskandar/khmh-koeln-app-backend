<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Workflow\Identity\Model\AccountReference;

final readonly class ServiceInsight
{
    /**
     * @param list<AccountReference> $leaders
     */
    public function __construct(
        public array $leaders,
        public ?NextMeeting $next,
        public AttendanceTrend $trend,
    ) {
    }
}
