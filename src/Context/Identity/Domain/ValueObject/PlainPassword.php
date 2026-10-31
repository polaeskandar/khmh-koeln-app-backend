<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\ValueObject;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use SensitiveParameter;

final readonly class PlainPassword
{
    private const int MIN_LENGTH = 10;

    private const int MAX_LENGTH = 200;

    private function __construct(
        #[SensitiveParameter]
        public string $value,
    ) {
    }

    public static function of(#[SensitiveParameter] string $value): self
    {
        $length = InputText::of($value)->length();

        if ($length < self::MIN_LENGTH) {
            throw InvalidInput::field('password', 'accounts.passwordTooShort', ['min' => self::MIN_LENGTH]);
        }

        if ($length > self::MAX_LENGTH) {
            throw InvalidInput::tooLong('password', self::MAX_LENGTH);
        }

        return new self($value);
    }
}
