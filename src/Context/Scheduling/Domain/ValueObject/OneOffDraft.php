<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class OneOffDraft
{
    public function __construct(
        public Roster $roster,
        public ?string $title,
        public ?string $location,
        public string $startsAt,
        public ?string $endsAt,
    ) {
    }

    public function details(): MeetingDetails
    {
        return new MeetingDetails(
            name: InputText::of($this->title)->collapsed()->toString(),
            description: null,
            location: InputText::of($this->location)->trimmed()->toStringOrNull(),
        );
    }
}
