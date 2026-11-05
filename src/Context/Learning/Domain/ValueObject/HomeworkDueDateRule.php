<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Time\Model\LocalDate;

final readonly class HomeworkDueDateRule
{
    /**
     * @return array<string, array{code: string, params?: array<string, string|int|float|bool|null>}>
     */
    public static function errors(?string $sent, ?LocalDate $stored, LocalDate $teachingDate): array
    {
        try {
            $dueDate = HomeworkDueDate::of($sent);
        } catch (InvalidInput $invalid) {
            return $invalid->fieldErrors;
        }

        $unchanged = $dueDate?->toString() === $stored?->toString();
        $early = !$unchanged && $dueDate?->isBefore($teachingDate) === true;

        return $early ? ['dueDate' => ['code' => 'homework.dueBeforeLesson']] : [];
    }
}
