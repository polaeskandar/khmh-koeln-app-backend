<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalTime;

final readonly class TimesDraft
{
    private function __construct(
        private ?string $startTime,
        private ?string $endTime,
        private bool $replacesEnd,
    ) {
    }

    public static function of(?string $startTime, ?string $endTime): self
    {
        return new self($startTime, $endTime, replacesEnd: true);
    }

    public static function change(?string $startTime, ?string $endTime, bool $endTimeSent): self
    {
        return new self($startTime, $endTime, replacesEnd: $endTimeSent);
    }

    public function merged(?TimeSlot $stored): TimeSlot
    {
        $start = InputText::of(
            $this->startTime ?? $stored?->startTime->toString(),
        )->trimmed()->toStringOrNull() ?? throw InvalidInput::field('times.startTime', 'validation.chooseTime');

        $end = $this->replacesEnd
            ? InputText::of($this->endTime)->trimmed()->toStringOrNull()
            : $stored?->endTime?->toString();

        if ($end !== null && $end <= $start) {
            throw InvalidInput::field('times.endTime', 'meetings.endAfterStart');
        }

        return new TimeSlot(LocalTime::of($start), $end === null ? null : LocalTime::of($end));
    }
}
