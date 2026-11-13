<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\ParentAccessList;

final readonly class ParentAccessListResponse
{
    /**
     * @param list<ParentAccessItemResponse> $items
     * @param list<array{id: string, name: string, username: string}> $accounts
     */
    public function __construct(
        public array $items,
        public array $accounts,
        public string $today,
    ) {
    }

    public static function of(ParentAccessList $list): self
    {
        return new self(ParentAccessItemResponse::listOf($list->items), $list->accounts, $list->today);
    }
}
