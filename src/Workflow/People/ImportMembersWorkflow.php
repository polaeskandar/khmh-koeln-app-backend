<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\People\Application\Service\MemberImports;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Files\Client\CsvFiles;
use App\Workflow\People\Model\ImportReport;

final readonly class ImportMembersWorkflow
{
    public function __construct(
        private ReadMemberImportWorkflow $readMemberImport,
        private PlacementChoices $placements,
        private MemberImports $imports,
        private CsvFiles $files,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $serviceIds = ($this->readMemberImport)($path, $duplicates)->serviceIds();
        $plan = $this->placements->whileKept($serviceIds, fn (): ImportPlan => $this->saved($path, $duplicates));

        if ($plan->isValid()) {
            $this->files->delete($path);
        }

        return ImportReport::of($plan, done: 'Imported');
    }

    private function saved(string $path, string $duplicates): ImportPlan
    {
        $plan = ($this->readMemberImport)($path, $duplicates);

        if ($plan->isValid()) {
            $this->imports->save($plan);
        }

        return $plan;
    }
}
