<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\AccountRequest;
use App\Workflow\Identity\Model\AccountRequestList;
use App\Workflow\Identity\Model\RoleSummary;

final readonly class AccountRequestListResponse
{
    /**
     * @param list<AccountRequest> $items
     * @param list<RoleSummary> $roles
     */
    public function __construct(
        public array $items,
        public array $roles,
        public string $today,
    ) {
    }

    public static function of(AccountRequestList $list): self
    {
        return new self($list->items, $list->roles, $list->today);
    }
}
