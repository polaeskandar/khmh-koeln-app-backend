<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;

final readonly class MaterialProblems
{
    private const int LABEL_LENGTH = 120;

    private const string URL = '#^https?://\S+$#i';

    /**
     * @return iterable<string, string>
     */
    public static function of(Material $material): iterable
    {
        if (InputText::of($material->label)->length() > self::LABEL_LENGTH) {
            yield 'label' => 'materials.labelTooLong';
        }

        if ($material->url !== null && !Text::of($material->url)->matches(self::URL)) {
            yield 'url' => 'materials.url';
        }
    }
}
