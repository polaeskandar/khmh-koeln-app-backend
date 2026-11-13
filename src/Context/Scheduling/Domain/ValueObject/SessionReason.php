<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;

final readonly class SessionReason
{
    private const int MAX_LENGTH = 200;

    private function __construct(
        public ?string $value,
    ) {
    }

    public static function of(?string $value): self
    {
        $clean = InputText::of($value)->trimmed();

        if ($clean->length() > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('reason', self::MAX_LENGTH);
        }

        return new self($clean->toStringOrNull());
    }
}
