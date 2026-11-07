<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;

final readonly class MaterialProblems
{
    private const int LABEL_LENGTH = 120;

    private const int URL_LENGTH = 2048;

    private const string URL = '~^https?://[^\s/?#@<>"\p{C}]+(?:[/?#][^\s<>"\p{C}]*)?$~iu';

    /**
     * @return iterable<string, array{code: string, params?: array<string, int>}>
     */
    public static function of(Material $material): iterable
    {
        $label = self::labelProblem($material);
        $url = self::urlProblem($material->url);

        if ($label !== null) {
            yield 'label' => $label;
        }

        if ($url !== null) {
            yield 'url' => $url;
        }
    }

    /**
     * @return array{code: string}|null
     */
    private static function labelProblem(Material $material): ?array
    {
        return match (true) {
            $material->label === $material->url => null,
            InputText::of($material->label)->length() > self::LABEL_LENGTH => ['code' => 'materials.labelTooLong'],
            default => null,
        };
    }

    /**
     * @return array{code: string, params?: array<string, int>}|null
     */
    private static function urlProblem(?string $url): ?array
    {
        return match (true) {
            $url === null => null,
            InputText::of($url)->length() > self::URL_LENGTH => [
                'code' => 'validation.tooLong',
                'params' => ['max' => self::URL_LENGTH],
            ],
            !Text::of($url)->matches(self::URL) => ['code' => 'materials.url'],
            default => null,
        };
    }
}
