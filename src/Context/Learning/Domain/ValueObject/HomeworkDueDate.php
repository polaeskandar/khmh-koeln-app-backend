<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Exception\InvalidLocalDate;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkDueDate
{
    public static function of(?string $sent, ?LocalDate $stored): ?LocalDate
    {
        return match ($sent) {
            null => $stored,
            '' => null,
            default => self::parsed($sent),
        };
    }

    /**
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    public static function errors(?string $sent, ?LocalDate $stored, LocalDate $teachingDate): array
    {
        try {
            $dueDate = self::of($sent, $stored);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        $moved = $dueDate?->toString() !== $stored?->toString();
        $early = $dueDate?->isBefore($teachingDate) === true;

        return $moved && $early ? ['dueDate' => ['code' => 'homework.dueBeforeLesson']] : [];
    }

    private static function parsed(string $sent): LocalDate
    {
        try {
            return LocalDate::of($sent);
        } catch (InvalidLocalDate) {
            throw InvalidInput::field('dueDate', 'validation.invalidDate');
        }
    }
}
