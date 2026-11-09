<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\RoleList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class RoleListResponse
{
    /**
     * @param list<RoleRowResponse> $items
     */
    public function __construct(
        public array $items,
    ) {
    }

    public static function of(RoleList $list): self
    {
        return new self(new ArrayCollection($list->items)->map(RoleRowResponse::of(...))->getValues());
    }
}
