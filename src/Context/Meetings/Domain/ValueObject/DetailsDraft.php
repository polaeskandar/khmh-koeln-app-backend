<?php

declare(strict_types=1);

namespace App\Context\Meetings\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;

final readonly class DetailsDraft
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $location,
    ) {
    }

    public function merged(?MeetingDetails $stored): MeetingDetails
    {
        $name = InputText::of(
            $this->name ?? $stored?->name,
        )->collapsed()->toStringOrNull() ?? throw InvalidInput::field('details.name', 'meetings.scheduleNameRequired');

        return new MeetingDetails(
            $name,
            self::optional($this->description, $stored?->description),
            self::optional($this->location, $stored?->location),
        );
    }

    private static function optional(?string $sent, ?string $stored): ?string
    {
        return $sent === null ? $stored : InputText::of($sent)->trimmed()->toStringOrNull();
    }
}
