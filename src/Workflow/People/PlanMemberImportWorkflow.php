<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\RowPlacements;
use App\Context\People\Application\Service\MemberImports;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Files\Client\CsvFiles;
use App\Workflow\People\Model\ImportedRows;
use App\Workflow\People\Model\ImportPolicies;
use App\Workflow\People\Model\ImportReport;

final readonly class PlanMemberImportWorkflow
{
    public function __construct(
        private CsvFiles $files,
        private RowPlacements $placements,
        private MemberImports $imports,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $policy = ImportPolicies::of($duplicates);
        $values = $this->files->rows($path);
        $imported = ImportedRows::of($values, $this->placements->named($values));

        $plan = $imported->errors === []
            ? $this->imports->plan($imported->rows, $policy)
            : ImportPlan::refused($imported->errors);

        return ImportReport::of($plan, done: 'Would import');
    }
}
