<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkDueDate
{
    public static function of(?string $sent): ?LocalDate
    {
        $text = InputText::of($sent)->trimmed()->toStringOrNull();

        try {
            return $text === null ? null : LocalDate::of($text);
        } catch (InvalidLocalDate) {
            throw InvalidInput::field('dueDate', 'validation.invalidDate');
        }
    }
}
