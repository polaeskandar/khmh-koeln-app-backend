<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Workflow\Privacy\Model\DataRequestList;

final readonly class DataRequestListResponse
{
    /**
     * @param list<DataRequestRowResponse> $open
     * @param list<DataRequestRowResponse> $closed
     */
    public function __construct(
        public string $today,
        public array $open,
        public array $closed,
    ) {
    }

    public static function of(DataRequestList $list): self
    {
        return new self(
            $list->today,
            DataRequestRowResponse::listOf($list->open),
            DataRequestRowResponse::listOf($list->closed),
        );
    }
}
