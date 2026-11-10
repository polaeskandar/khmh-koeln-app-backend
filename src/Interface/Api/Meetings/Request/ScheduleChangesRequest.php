<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Workflow\Meetings\Model\ScheduleChanges;
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
        #[Assert\Choice(callback: [RosterMode::class, 'values'], message: 'meetings.chooseRosterMode')]
        public ?string $rosterMode = null,
    ) {
    }

    public function changes(): ScheduleChanges
    {
        return new ScheduleChanges(
            $this->details->choice(),
            $this->recurrence->choice(),
            $this->times->choice(),
            $this->rosterMode,
        );
    }
}
