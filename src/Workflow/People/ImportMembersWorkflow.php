<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\MemberImports;
use App\Context\People\Domain\Enum\DuplicatePolicy;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Files\Client\CsvFiles;
use App\Workflow\People\Model\ImportedRows;
use App\Workflow\People\Model\ImportReport;
use InvalidArgumentException;

final readonly class ImportMembersWorkflow
{
    public function __construct(
        private CsvFiles $files,
        private PlacementChoices $placements,
        private StructureLookup $structure,
        private MemberImports $imports,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $values = $this->files->rows($path);
        $policy = self::policyOf($duplicates);

        $plan = $this->structure->whileKept(
            ImportedRows::of($values, $this->placements)->serviceIds(),
            fn (): ImportPlan => $this->saved($values, $policy),
        );

        if ($plan->isValid()) {
            $this->files->delete($path);
        }

        return ImportReport::of($plan, done: 'Imported');
    }

    /**
     * @param array<int, array<string, string>> $values
     */
    private function saved(array $values, DuplicatePolicy $policy): ImportPlan
    {
        $imported = ImportedRows::of($values, $this->placements);

        $plan = $imported->errors === []
            ? $this->imports->plan($imported->rows, $policy)
            : ImportPlan::refused($imported->errors);

        if ($plan->isValid()) {
            $this->imports->save($plan);
        }

        return $plan;
    }

    private static function policyOf(string $duplicates): DuplicatePolicy
    {
        $policy = DuplicatePolicy::tryFrom($duplicates);

        if ($policy === null) {
            throw new InvalidArgumentException('Pass --duplicates=skip or --duplicates=allow.');
        }

        return $policy;
    }
}
