<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\ScheduleChangeChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ScheduleChangesRequest
{
    public function __construct(
        #[Assert\Valid]
        public DetailsRequest $details = new DetailsRequest(),
        #[Assert\Valid]
        public RecurrenceRequest $recurrence = new RecurrenceRequest(),
        #[Assert\Valid]
        public TimesRequest $times = new TimesRequest(),
        #[Assert\NotBlank(message: 'meetings.chooseRosterMode')]
        #[Assert\Choice(choices: ['expected', 'open'], message: 'meetings.chooseRosterMode')]
        public ?string $rosterMode = null,
    ) {
    }

    public function choice(): ScheduleChangeChoice
    {
        return new ScheduleChangeChoice(
            $this->details->choice(),
            $this->recurrence->choice(),
            $this->times->choice(),
            $this->rosterMode,
        );
    }
}
