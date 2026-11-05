<?php

declare(strict_types=1);

namespace App\Workflow\MemberPortal\Model;

use App\Context\Meetings\Domain\ValueObject\MeetingDetails;
use App\Context\Meetings\Domain\ValueObject\PlannedDate;
use App\Library\Strings\Model\Text;

final readonly class MemberMeeting
{
    public function __construct(
        public string $key,
        public string $status,
        public MeetingTime $time,
        public MeetingGroup $group,
    ) {
    }

    public static function of(PlannedDate $date, MeetingDetails $details, MemberRoster $roster): self
    {
        $plannedKey = Text::format('%s:%s', $date->schedule->id()->toRfc4122(), $date->date->toString());

        return new self(
            $date->sessionId ?? $plannedKey->toString(),
            $date->status,
            MeetingTime::of($date),
            MeetingGroup::of($date, $details, $roster),
        );
    }

    public function sortKey(): string
    {
        return Text::format('%s|%s|%s', $this->time->date, $this->time->startTime, $this->group->group)->toString();
    }
}
