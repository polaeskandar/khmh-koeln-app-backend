<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\People\Domain\ValueObject\ImportColumns;
use App\Context\People\Domain\ValueObject\ImportLine;
use App\Context\People\Domain\ValueObject\ImportRow;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ImportedRows
{
    private const array COLUMNS = [
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'displayName' => 'display_name',
    ];

    /**
     * @param list<ImportRow> $rows
     * @param list<ImportLine> $errors
     */
    private function __construct(
        public array $rows,
        public array $errors,
    ) {
    }

    /**
     * @param array<int, array<string, string>> $values
     */
    public static function of(array $values, PlacementChoices $placements): self
    {
        $first = new ArrayCollection($values)->first();
        ImportColumns::ensureKnown($first === false ? [] : new ArrayCollection($first)->getKeys());
        $rows = [];
        $errors = [];

        foreach ($values as $line => $row) {
            try {
                $rows[] = self::rowOf($line, $row, $placements);
            } catch (InvalidInput $invalid) {
                $errors[] = self::errorOf($line, $invalid);
            }
        }

        return new self($rows, $errors);
    }

    /**
     * @param array<string, string> $row
     */
    private static function rowOf(int $line, array $row, PlacementChoices $placements): ImportRow
    {
        $name = PersonName::of(
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            InputText::of($row['display_name'] ?? '')->trimmed()->toStringOrNull(),
        );

        $chosen = $placements->named($row['service'] ?? '', $row['stage'] ?? '');
        $service = $chosen->service->name()->value;
        $where = $chosen->stage === null ? $service : $service . ' / ' . $chosen->stage->name()->value;

        return new ImportRow($line, $name, new Placement($chosen->service->id(), $chosen->stage?->id()), $where);
    }

    private static function errorOf(int $line, InvalidInput $invalid): ImportLine
    {
        $errors = new ArrayCollection($invalid->fieldErrors);
        $field = $errors->getKeys()[0] ?? '';

        return new ImportLine($line, (self::COLUMNS[$field] ?? $field) . ': ' . ($errors->get($field)['code'] ?? ''));
    }
}
