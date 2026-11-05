<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\MemberAccountList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberAccountListResponse
{
    /**
     * @param list<MemberAccountItemResponse> $items
     */
    private function __construct(
        public string $today,
        public array $items,
    ) {
    }

    public static function of(MemberAccountList $list): self
    {
        return new self(
            $list->today,
            new ArrayCollection($list->items)->map(MemberAccountItemResponse::of(...))->getValues(),
        );
    }
}
