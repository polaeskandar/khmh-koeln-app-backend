<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class OptionalText
{
    public static function of(?string $sent): ?string
    {
        return InputText::of($sent)->trimmed()->toStringOrNull();
    }
}
