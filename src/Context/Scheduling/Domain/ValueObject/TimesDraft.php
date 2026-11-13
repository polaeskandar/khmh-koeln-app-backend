<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalTime;

final readonly class TimesDraft
{
    public function __construct(
        public ?string $startTime,
        public ?string $endTime,
    ) {
    }

    public function slot(): TimeSlot
    {
        $start = InputText::of($this->startTime)->trimmed()->toStringOrNull() ?? throw InvalidInput::field(
            'times.startTime',
            'validation.chooseTime',
        );

        $end = InputText::of($this->endTime)->trimmed()->toStringOrNull();

        if ($end !== null && $end <= $start) {
            throw InvalidInput::field('times.endTime', 'meetings.endAfterStart');
        }

        return new TimeSlot(LocalTime::of($start), $end === null ? null : LocalTime::of($end));
    }
}
