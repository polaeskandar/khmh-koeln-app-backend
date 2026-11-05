<?php

declare(strict_types=1);

namespace App\Interface\Api\MemberPortal\Response;

use App\Workflow\MemberPortal\Model\MemberMeetingList;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberMeetingListResponse
{
    /**
     * @param list<MemberMeetingResponse> $items
     */
    private function __construct(
        public string $today,
        public string $from,
        public string $to,
        public array $items,
    ) {
    }

    public static function of(MemberMeetingList $list): self
    {
        return new self(
            $list->today,
            $list->from,
            $list->to,
            new ArrayCollection($list->items)->map(MemberMeetingResponse::of(...))->getValues(),
        );
    }
}
