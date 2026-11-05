<?php

declare(strict_types=1);

namespace App\Platform\Http\Model;

use App\Library\Strings\Model\InputText;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;

final readonly class Origins
{
    private const string SEPARATOR = ',';

    private const string EXACT_ORIGIN = '#^(https?|capacitor)://[a-z0-9.-]+(:[0-9]{1,5})?$#';

    private const string URL_ORIGIN = '#^(https?://[^/?\#]+).*$#';

    /**
     * @return list<string>
     */
    public static function of(string $list): array
    {
        return new ArrayCollection(Text::of($list)->split(self::SEPARATOR))
            ->map(self::trimmed(...))
            ->filter(self::isPresent(...))
            ->map(self::exact(...))
            ->getValues();
    }

    public static function ofUrl(string $url): string
    {
        return Text::of($url)->replaceMatches(self::URL_ORIGIN, replacement: '$1')->toString();
    }

    private static function trimmed(string $entry): string
    {
        return InputText::of($entry)->trimmed()->toString();
    }

    private static function isPresent(string $entry): bool
    {
        return $entry !== '';
    }

    private static function exact(string $origin): string
    {
        if (Text::of($origin)->matches(self::EXACT_ORIGIN)) {
            return $origin;
        }

        throw new InvalidArgumentException(
            Text::format('"%s" is not an exact scheme://host[:port] origin.', $origin)->toString(),
        );
    }
}
