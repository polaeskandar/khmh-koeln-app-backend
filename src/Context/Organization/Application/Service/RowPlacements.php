<?php

declare(strict_types=1);

namespace App\Context\Organization\Application\Service;

use App\Context\Organization\Domain\ValueObject\ChosenPlacement;
use App\Library\Problem\Exception\InvalidInput;

final readonly class RowPlacements
{
    public function __construct(
        private PlacementChoices $placements,
    ) {
    }

    /**
     * @param array<int, array<string, string>> $rows
     *
     * @return array<int, ChosenPlacement|InvalidInput>
     */
    public function named(array $rows): array
    {
        $placed = [];

        foreach ($rows as $line => $row) {
            try {
                $placed[$line] = $this->placements->named($row['service'] ?? '', $row['stage'] ?? '');
            } catch (InvalidInput $invalid) {
                $placed[$line] = $invalid;
            }
        }

        return $placed;
    }
}
