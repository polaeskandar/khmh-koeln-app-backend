<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use App\Workflow\Scheduling\Model\NewSchedule;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateScheduleRequest
{
    public function __construct(
        #[Assert\Valid]
        public RosterRequest $roster = new RosterRequest(),
        #[Assert\Valid]
        public DetailsRequest $details = new DetailsRequest(),
        #[Assert\Valid]
        public RecurrenceRequest $recurrence = new RecurrenceRequest(),
        #[Assert\Valid]
        public TimesRequest $times = new TimesRequest(),
    ) {
    }

    public function schedule(): NewSchedule
    {
        return new NewSchedule(
            $this->roster->choice(),
            $this->details->choice(),
            $this->recurrence->choice(),
            $this->times->choice(),
        );
    }
}
