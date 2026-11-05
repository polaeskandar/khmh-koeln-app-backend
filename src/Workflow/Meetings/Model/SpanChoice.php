<?php

declare(strict_types=1);

namespace App\Workflow\Meetings\Model;

use App\Context\Meetings\Domain\ValueObject\DateSpan;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalDate;

final readonly class SpanChoice
{
    public function __construct(
        public ?string $startsOn,
        public ?string $endsOn,
    ) {
    }

    public function span(): DateSpan
    {
        $endsOn = InputText::of($this->endsOn)->trimmed()->toStringOrNull();

        return new DateSpan(LocalDate::of($this->startsOn ?? ''), $endsOn === null ? null : LocalDate::of($endsOn));
    }
}
