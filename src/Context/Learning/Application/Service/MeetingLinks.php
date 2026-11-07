<?php

declare(strict_types=1);

namespace App\Context\Learning\Application\Service;

use App\Context\Learning\Domain\ValueObject\MeetingChoice;
use App\Context\Learning\Domain\ValueObject\MeetingLink;
use App\Context\Learning\Domain\ValueObject\OfferedMeeting;
use Symfony\Component\Uid\Uuid;

final readonly class MeetingLinks
{
    public static function to(Uuid $stageId, ?OfferedMeeting $offered, ?MeetingLink $current): MeetingLink
    {
        if ($offered?->belongsTo($stageId) !== true || $offered->isNewlyCanceled($current)) {
            throw MeetingChoice::refusal('lessons.chooseMeeting');
        }

        if ($offered->isNewlyUnscheduled($current)) {
            throw MeetingChoice::refusal('lessons.noMeetingThatDay', ['name' => $offered->name]);
        }

        return $offered->link;
    }
}
