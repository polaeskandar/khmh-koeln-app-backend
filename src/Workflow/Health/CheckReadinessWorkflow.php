<?php

declare(strict_types=1);

namespace App\Workflow\Health;

use App\Platform\Health\Contract\DatabaseProbe;
use App\Workflow\Health\Model\Readiness;

final readonly class CheckReadinessWorkflow
{
    private const int TIMEOUT_SECONDS = 2;

    public function __construct(
        private DatabaseProbe $database,
    ) {
    }

    public function __invoke(): Readiness
    {
        $database = $this->database->isAvailable(self::TIMEOUT_SECONDS) ? Readiness::OK : Readiness::UNAVAILABLE;

        return new Readiness(status: $database, checks: ['database' => $database]);
    }
}
