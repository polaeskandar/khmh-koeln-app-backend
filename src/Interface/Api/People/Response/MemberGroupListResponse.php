<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\MemberGroupList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberGroupListResponse
{
    /**
     * @param list<MemberGroupResponse> $items
     */
    private function __construct(
        public string $today,
        public array $items,
    ) {
    }

    public static function of(MemberGroupList $list): self
    {
        return new self(
            $list->today,
            new ArrayCollection($list->items)->map(MemberGroupResponse::of(...))->getValues(),
        );
    }
}
