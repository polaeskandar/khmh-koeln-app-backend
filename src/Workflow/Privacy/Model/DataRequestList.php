<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Library\Collections\Model\Orderings;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class DataRequestList
{
    private const int CLOSED_SHOWN = 200;

    /**
     * @param list<DataRequestRow> $open
     * @param list<DataRequestRow> $closed
     */
    public function __construct(
        public string $today,
        public array $open,
        public array $closed,
    ) {
    }

    /**
     * @param list<DataRequestRow> $rows
     */
    public static function of(array $rows, string $today): self
    {
        $all = new ArrayCollection($rows);

        $closed = Orderings::byKeysDescending(
            $all->filter(static fn (DataRequestRow $row): bool => !$row->header->isOpen()),
            keysOf: static fn (DataRequestRow $row): array => [
                $row->closing->closedAt?->getTimestamp() ?? 0,
                $row->header->id,
            ],
        );

        return new self(
            $today,
            $all->filter(static fn (DataRequestRow $row): bool => $row->header->isOpen())->getValues(),
            new ArrayCollection(new ArrayCollection($closed)->slice(
                offset: 0,
                length: self::CLOSED_SHOWN,
            ))->getValues(),
        );
    }
}
