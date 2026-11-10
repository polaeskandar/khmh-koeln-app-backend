<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Validation;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkRule
{
    private const int TITLE_LENGTH = 120;

    private const int INSTRUCTIONS_LENGTH = 2000;

    public static function ensureTitle(string $title): void
    {
        $length = InputText::of($title)->length();

        if ($length === 0) {
            throw InvalidInput::field('title', 'homework.titleRequired');
        }

        if ($length > self::TITLE_LENGTH) {
            throw InvalidInput::tooLong('title', self::TITLE_LENGTH);
        }
    }

    public static function ensureInstructions(string $instructions): void
    {
        $length = InputText::of($instructions)->length();

        if ($length === 0) {
            throw InvalidInput::field('instructions', 'homework.instructionsRequired');
        }

        if ($length > self::INSTRUCTIONS_LENGTH) {
            throw InvalidInput::field('instructions', 'homework.instructionsTooLong');
        }
    }

    public static function ensureDueDate(?LocalDate $dueDate, ?LocalDate $stored, LocalDate $teachingDate): void
    {
        $changed = $dueDate?->toString() !== $stored?->toString();

        if ($changed && $dueDate?->isBefore($teachingDate) === true) {
            throw InvalidInput::field('dueDate', 'homework.dueBeforeLesson');
        }
    }
}
