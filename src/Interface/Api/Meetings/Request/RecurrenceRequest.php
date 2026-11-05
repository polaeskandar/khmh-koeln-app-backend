<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\RecurrenceChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecurrenceRequest
{
    /**
     * @param list<int> $weekdays
     */
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Choice(choices: ['weekly', 'monthly'], message: 'validation.invalidChoice')]
        public ?string $frequency = null,
        public ?int $interval = null,
        public array $weekdays = [],
        public ?int $monthWeek = null,
        #[Assert\Valid]
        public SpanRequest $span = new SpanRequest(),
    ) {
    }

    public function choice(): RecurrenceChoice
    {
        return new RecurrenceChoice(
            $this->frequency,
            $this->interval,
            $this->weekdays,
            $this->monthWeek,
            $this->span->choice(),
        );
    }
}
