<?php

declare(strict_types=1);

namespace App\Context\Identity\Domain\Validation;

use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;

final readonly class UsernameRule
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 32;

    private const string SHAPE = '/^[a-z](?:[a-z0-9]|[._-](?=[a-z0-9]))*$/';

    public static function ensureValid(string $username, string $field = 'username'): void
    {
        $length = InputText::of($username)->length();

        if ($length === 0) {
            throw InvalidInput::field($field, 'accounts.usernameRequired');
        }

        if ($length < self::MIN_LENGTH) {
            throw InvalidInput::field($field, 'accounts.usernameTooShort', ['min' => self::MIN_LENGTH]);
        }

        if ($length > self::MAX_LENGTH) {
            throw InvalidInput::tooLong($field, self::MAX_LENGTH);
        }

        if (!Text::of($username)->matches(self::SHAPE)) {
            throw InvalidInput::field($field, 'accounts.usernameInvalid');
        }
    }
}
