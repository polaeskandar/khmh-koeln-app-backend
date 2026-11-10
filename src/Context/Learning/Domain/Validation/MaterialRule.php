<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\Validation;

use App\Context\Learning\Domain\ValueObject\Material;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;

final readonly class MaterialRule
{
    private const int LABEL_LENGTH = 120;

    private const int URL_LENGTH = 2048;

    private const string URL = '~^https?://[^\s/?#@<>"\p{C}]+(?:[/?#][^\s<>"\p{C}]*)?$~iu';

    public static function ensureValid(Material $material): void
    {
        InvalidInput::all(
            static fn () => self::ensureLabel($material),
            static fn () => self::ensureUrl($material->url),
        );
    }

    private static function ensureLabel(Material $material): void
    {
        if ($material->label === $material->url) {
            return;
        }

        if (InputText::of($material->label)->length() > self::LABEL_LENGTH) {
            throw InvalidInput::field('label', 'materials.labelTooLong');
        }
    }

    private static function ensureUrl(?string $url): void
    {
        if ($url === null) {
            return;
        }

        if (InputText::of($url)->length() > self::URL_LENGTH) {
            throw InvalidInput::tooLong('url', self::URL_LENGTH);
        }

        if (!Text::of($url)->matches(self::URL)) {
            throw InvalidInput::field('url', 'materials.url');
        }
    }
}
