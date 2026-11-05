<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\PlacementChoices;
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
        private MemberImports $imports,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportReport
    {
        $imported = ImportedRows::of($this->files->rows($path), $this->placements);

        $plan = $imported->errors === []
            ? $this->imports->plan($imported->rows, self::policyOf($duplicates))
            : ImportPlan::refused($imported->errors);

        if ($plan->isValid()) {
            $this->imports->save($plan);
            $this->files->delete($path);
        }

        return ImportReport::of($plan, done: 'Imported');
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
