<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;

final readonly class TextDraft
{
    public function __construct(
        public ?string $title,
        public ?string $summary,
        public ?string $passage,
        public ?string $memoryVerse,
    ) {
    }

    public function text(): LessonText
    {
        $title = InputText::of($this->title)->collapsed()->toStringOrNull() ?? throw InvalidInput::field(
            'details.title',
            'lessons.titleRequired',
        );

        return new LessonText(
            $title,
            OptionalText::of($this->summary),
            OptionalText::of($this->passage),
            OptionalText::of($this->memoryVerse),
        );
    }
}
