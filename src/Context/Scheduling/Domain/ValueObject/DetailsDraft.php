<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

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

    public function details(): MeetingDetails
    {
        $name = InputText::of($this->name)->collapsed()->toStringOrNull() ?? throw InvalidInput::field(
            'details.name',
            'meetings.scheduleNameRequired',
        );

        return new MeetingDetails($name, self::optional($this->description), self::optional($this->location));
    }

    private static function optional(?string $text): ?string
    {
        return InputText::of($text)->trimmed()->toStringOrNull();
    }
}
