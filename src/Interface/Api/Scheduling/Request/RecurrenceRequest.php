<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use App\Context\Scheduling\Domain\Enum\Frequency;
use App\Workflow\Scheduling\Model\RecurrenceChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RecurrenceRequest
{
    /**
     * @param list<int> $weekdays
     */
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Choice(callback: [Frequency::class, 'values'], message: 'validation.invalidChoice')]
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
