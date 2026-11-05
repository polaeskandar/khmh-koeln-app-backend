<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Workflow\People\Model\ImportReport;

final readonly class PlanMemberImportWorkflow
{
    public function __construct(
        private ReadMemberImportWorkflow $readMemberImport,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        return ImportReport::of(($this->readMemberImport)($path, $duplicates), done: 'Would import');
    }
}
