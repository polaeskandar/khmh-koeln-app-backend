<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingLinks
{
    public static function toSession(Uuid $stageId, ?OfferedMeeting $offered, ?MeetingLink $current): MeetingLink
    {
        return self::usable($stageId, $offered, $current)->link;
    }

    public static function toSchedule(Uuid $stageId, ?OfferedMeeting $offered, ?MeetingLink $current): MeetingLink
    {
        $meeting = self::usable($stageId, $offered, $current);

        if (!$meeting->scheduled) {
            throw MeetingChoice::refusal('lessons.noMeetingThatDay', ['name' => $meeting->name]);
        }

        return $meeting->link;
    }

    private static function usable(Uuid $stageId, ?OfferedMeeting $offered, ?MeetingLink $current): OfferedMeeting
    {
        if ($offered?->belongsTo($stageId) !== true || $offered->isNewlyCanceled($current)) {
            throw MeetingChoice::refusal('lessons.chooseMeeting');
        }

        return $offered;
    }
}
