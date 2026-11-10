<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\StaffAccountList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StaffAccountListResponse
{
    /**
     * @param list<StaffAccountResponse> $items
     */
    public function __construct(
        public array $items,
        public string $today,
    ) {
    }

    public static function of(StaffAccountList $list): self
    {
        return new self(
            new ArrayCollection($list->items)->map(StaffAccountResponse::of(...))->getValues(),
            $list->today,
        );
    }
}
