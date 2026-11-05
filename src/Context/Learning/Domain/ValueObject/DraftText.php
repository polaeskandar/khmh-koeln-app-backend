<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;

final readonly class DraftText
{
    public static function merged(?string $sent, ?string $stored): ?string
    {
        return $sent === null ? $stored : InputText::of($sent)->trimmed()->toStringOrNull();
    }
}
