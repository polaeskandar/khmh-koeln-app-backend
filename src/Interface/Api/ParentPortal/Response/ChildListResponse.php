<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ChildList;
use App\Workflow\ParentPortal\Model\ChildSummary;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ChildListResponse
{
    /**
     * @param list<ChildSummaryResponse> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }

    public static function of(ChildList $list): self
    {
        $itemOf = static fn (ChildSummary $child): ChildSummaryResponse => new ChildSummaryResponse($child);

        return new self($list->today, new ArrayCollection($list->items)->map($itemOf)->getValues());
    }
}
