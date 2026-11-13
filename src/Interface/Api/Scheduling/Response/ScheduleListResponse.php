<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Response;

use App\Workflow\Scheduling\Model\ScheduleList;
use App\Workflow\Scheduling\Model\ScheduleSummary;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class ScheduleListResponse
{
    /**
     * @param list<ScheduleResponse> $items
     */
    public function __construct(
        public string $today,
        public array $items,
    ) {
    }

    public static function of(ScheduleList $list): self
    {
        return new self(
            $list->today,
            new ArrayCollection($list->items)->map(
                static fn (ScheduleSummary $summary): ScheduleResponse => new ScheduleResponse($summary),
            )->getValues(),
        );
    }
}
