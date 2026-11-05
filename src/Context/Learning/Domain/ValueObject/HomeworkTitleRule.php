<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class HomeworkTitleRule
{
    private const int MAX_LENGTH = 120;

    /**
     * @return array<string, array{code: string, params?: array<string, int>}>
     */
    public static function errors(string $title): array
    {
        $length = InputText::of($title)->length();

        return match (true) {
            $length === 0 => ['title' => ['code' => 'homework.titleRequired']],
            $length > self::MAX_LENGTH => [
                'title' => ['code' => 'validation.tooLong', 'params' => ['max' => self::MAX_LENGTH]],
            ],
            default => [],
        };
    }
}
