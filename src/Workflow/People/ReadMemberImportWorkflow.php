<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Context\People\Application\Service\MemberImports;
use App\Context\People\Domain\Enum\DuplicatePolicy;
use App\Context\People\Domain\ValueObject\ImportPlan;
use App\Library\Files\Client\CsvFiles;
use App\Library\Problem\Exception\InvalidInput;
use App\Workflow\People\Model\ImportedRows;
use InvalidArgumentException;

final readonly class ReadMemberImportWorkflow
{
    private const string POLICIES = 'Pass --duplicates=skip or --duplicates=allow.';

    public function __construct(
        private CsvFiles $files,
        private PlacementChoices $placements,
        private MemberImports $imports,
    ) {
    }

    public function __invoke(string $path, string $duplicates): ImportPlan
    {
        $policy = DuplicatePolicy::tryFrom($duplicates) ?? throw new InvalidArgumentException(self::POLICIES);
        $values = $this->files->rows($path);
        $imported = ImportedRows::of($values, $this->placementsOf($values));

        return $imported->errors === []
            ? $this->imports->plan($imported->rows, $policy)
            : ImportPlan::refused($imported->errors);
    }

    /**
     * @param array<int, array<string, string>> $values
     *
     * @return array<int, ChosenPlacement|InvalidInput>
     */
    private function placementsOf(array $values): array
    {
        $placed = [];

        foreach ($values as $line => $row) {
            try {
                $placed[$line] = $this->placements->named($row['service'] ?? '', $row['stage'] ?? '');
            } catch (InvalidInput $invalid) {
                $placed[$line] = $invalid;
            }
        }

        return $placed;
    }
}
