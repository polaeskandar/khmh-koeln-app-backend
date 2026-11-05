<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\StaffAccount;
use App\Workflow\Identity\Model\StaffAccountList;

final readonly class StaffAccountListResponse
{
    /**
     * @param list<StaffAccount> $items
     */
    public function __construct(
        public array $items,
        public string $today,
    ) {
    }

    public static function of(StaffAccountList $list): self
    {
        return new self($list->items, $list->today);
    }
}
