<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\AccountRequest;
use App\Workflow\Identity\Model\AccountRequestList;

final readonly class AccountRequestListResponse
{
    /**
     * @param list<AccountRequest> $items
     */
    public function __construct(
        public array $items,
        public string $today,
    ) {
    }

    public static function of(AccountRequestList $list): self
    {
        return new self($list->items, $list->today);
    }
}
