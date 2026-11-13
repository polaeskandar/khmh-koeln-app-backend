<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Context\Scheduling\Domain\Entity\Session;
use App\Context\Scheduling\Domain\ValueObject\PlannedDate;
use App\Workflow\Scheduling\Model\MeetingTime;
use App\Workflow\Scheduling\Model\SessionProgress;

final readonly class SessionMeeting
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $location,
        public MeetingTime $time,
        public SessionProgress $progress,
    ) {
    }

    public static function of(Session $session): self
    {
        $plan = $session->plan();

        return new self(
            id: $session->id()->toRfc4122(),
            title: $plan->title,
            location: $plan->location,
            time: MeetingTime::of(PlannedDate::ofSession($session)),
            progress: SessionProgress::of($session),
        );
    }
}
