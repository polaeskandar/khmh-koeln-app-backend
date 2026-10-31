<?php

declare(strict_types=1);

namespace App\Interface\Application\Health\UseCase\Query\CheckReadiness;

use App\Interface\Application\Health\Model\Readiness;
use App\Platform\Health\Contract\DatabaseProbe;

final readonly class CheckReadinessHandler
{
    public function __construct(
        private DatabaseProbe $database,
    ) {
    }

    public function __invoke(CheckReadinessQuery $query): Readiness
    {
        $database = $this->database->isAvailable($query->timeoutSeconds) ? Readiness::OK : Readiness::UNAVAILABLE;

        return new Readiness($database, ['database' => $database]);
    }
}
