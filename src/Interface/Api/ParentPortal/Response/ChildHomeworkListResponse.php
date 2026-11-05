<?php

declare(strict_types=1);

namespace App\Interface\Api\ParentPortal\Response;

use App\Workflow\ParentPortal\Model\ChildHomeworkList;
use App\Workflow\ParentPortal\Model\ParentHomeworkItem;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ChildHomeworkListResponse
{
    /**
     * @param list<ParentHomeworkItemResponse> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }

    public static function of(ChildHomeworkList $list): self
    {
        $itemOf = static fn (ParentHomeworkItem $item): ParentHomeworkItemResponse => new ParentHomeworkItemResponse(
            $item,
        );

        return new self($list->today, new ArrayCollection($list->items)->map($itemOf)->getValues());
    }
}
