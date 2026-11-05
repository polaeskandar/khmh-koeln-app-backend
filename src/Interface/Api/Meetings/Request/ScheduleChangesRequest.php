<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\ScheduleChanges;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScheduleChangesRequest
{
    public function __construct(
        #[Assert\Valid]
        public DetailsRequest $details = new DetailsRequest(),
        #[Assert\Valid]
        public ?RecurrenceRequest $recurrence = null,
        #[Assert\Valid]
        public TimesRequest $times = new TimesRequest(),
        #[Assert\Choice(choices: ['expected', 'open'], message: 'meetings.chooseRosterMode')]
        public ?string $rosterMode = null,
    ) {
    }

    public function changes(bool $endTimeSent): ScheduleChanges
    {
        return new ScheduleChanges(
            $this->details->choice(),
            $this->recurrence?->choice(),
            $this->times->choice($endTimeSent),
            $this->rosterMode,
        );
    }
}
