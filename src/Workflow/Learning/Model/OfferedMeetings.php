<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use App\Context\Meetings\Application\Service\MeetingLookup;
use App\Context\Meetings\Domain\ValueObject\MeetingSlot;

final readonly class OfferedMeetings
{
    public static function lookedUpIn(MeetingLookup $meetings, ?MeetingChoice $choice): ?OfferedMeeting
    {
        $slot = match (true) {
            $choice === null => null,
            $choice->sessionId !== null => $meetings->slotOfSession($choice->session()),
            $choice->scheduleId !== null => $meetings->slotOfSchedule($choice->schedule(), $choice->day()),
            default => null,
        };

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
