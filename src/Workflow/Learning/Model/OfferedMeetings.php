<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Meetings\Domain\ValueObject\MeetingSlot;

final readonly class OfferedMeetings
{
    public static function of(?MeetingSlot $slot): ?OfferedMeeting
    {
        return $slot === null ? null : self::offered($slot);
    }

    private static function offered(MeetingSlot $slot): OfferedMeeting
    {
        return new OfferedMeeting(
            new MeetingLink($slot->scheduleId(), $slot->date),
            stageId: $slot->stageId(),
            name: $slot->name(),
            scheduled: $slot->isScheduled(),
            canceled: $slot->isCanceled(),
        );
    }
}
