<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\RowPlacements;
use App\Context\People\Application\Service\MemberImports;
use App\Context\People\Domain\Enum\DuplicatePolicy;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Files\Client\CsvFiles;
use App\Workflow\People\Model\ImportedRows;
use App\Workflow\People\Model\ImportPolicies;
use App\Workflow\People\Model\ImportReport;

final readonly class ImportMembersWorkflow
{
    public function __construct(
        private CsvFiles $files,
        private PlacementChoices $placements,
        private RowPlacements $rows,
        private MemberImports $imports,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $policy = ImportPolicies::of($duplicates);
        $serviceIds = $this->planned($path, $policy)->serviceIds();
        $plan = $this->placements->whileServicesKept($serviceIds, fn (): ImportPlan => $this->saved($path, $policy));

        if ($plan->isValid()) {
            $this->files->delete($path);
        }

        return ImportReport::of($plan, done: 'Imported');
    }

    private function saved(string $path, DuplicatePolicy $policy): ImportPlan
    {
        $plan = $this->planned($path, $policy);

        if ($plan->isValid()) {
            $this->imports->save($plan);
        }

        return $plan;
    }

    private function planned(string $path, DuplicatePolicy $policy): ImportPlan
    {
        $values = $this->files->rows($path);
        $imported = ImportedRows::of($values, $this->rows->named($values));

        return $imported->errors === []
            ? $this->imports->plan($imported->rows, $policy)
            : ImportPlan::refused($imported->errors);
    }
}
