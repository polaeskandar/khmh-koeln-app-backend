<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

final readonly class ServiceInsight
{
    public function __construct(
        public string $serviceId,
        public ?NextMeeting $next,
        public AttendanceTrend $trend,
        public ArchiveImpact $impact,
    ) {
    }
}
