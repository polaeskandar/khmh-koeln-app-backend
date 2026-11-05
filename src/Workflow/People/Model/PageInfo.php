<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

final readonly class PageInfo
{
    public function __construct(
        public int $page,
        public int $pageSize,
        public int $total,
    ) {
    }
}
