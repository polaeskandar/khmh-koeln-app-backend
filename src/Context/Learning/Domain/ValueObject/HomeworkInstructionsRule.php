<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class HomeworkInstructionsRule
{
    private const int MAX_LENGTH = 2000;

    /**
     * @return array<string, array{code: string}>
     */
    public static function errors(string $instructions): array
    {
        $length = InputText::of($instructions)->length();

        return match (true) {
            $length === 0 => ['instructions' => ['code' => 'homework.instructionsRequired']],
            $length > self::MAX_LENGTH => ['instructions' => ['code' => 'homework.instructionsTooLong']],
            default => [],
        };
    }
}
