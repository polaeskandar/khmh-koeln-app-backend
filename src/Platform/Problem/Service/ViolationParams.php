<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Library\Types\Model\Type;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Validator\ConstraintViolationInterface;

final readonly class ViolationParams
{
    private const array IGNORED = ['value', 'value_length', 'hint', 'type'];

    /**
     * @return ReadableCollection<string, string|int|float|bool>
     */
    public static function of(ConstraintViolationInterface $violation): ReadableCollection
    {
        $params = $violation |> self::scalarParameters(...) |> self::named(...) |> self::shared(...);

        return new ArrayCollection([...LengthBounds::of($violation)->toArray(), ...$params->toArray()]);
    }

    /**
     * @return ReadableCollection<array-key, string|int|float|bool>
     */
    private static function scalarParameters(ConstraintViolationInterface $violation): ReadableCollection
    {
        return Collections::mapPresent(new ArrayCollection($violation->getParameters()), map: self::scalarOf(...));
    }

    /**
     * @param ReadableCollection<array-key, string|int|float|bool> $params
     *
     * @return ReadableCollection<string, string|int|float|bool>
     */
    private static function named(ReadableCollection $params): ReadableCollection
    {
        return Collections::rekeyed($params, keyOf: self::nameOf(...));
    }

    /**
     * @param ReadableCollection<string, string|int|float|bool> $params
     *
     * @return ReadableCollection<string, string|int|float|bool>
     */
    private static function shared(ReadableCollection $params): ReadableCollection
    {
        return Collections::withoutKeys($params, keys: self::IGNORED)->map(self::numberOf(...));
    }

    private static function scalarOf(mixed $value): string|int|float|bool|null
    {
        return Type::of($value)->toScalarOrNull();
    }

    private static function nameOf(int|string $placeholder): string
    {
        return Text::of((string) $placeholder)->replaceMatches('/^\{\{ (.+) }}$/', '$1')->toString();
    }

    private static function numberOf(string|int|float|bool $value): string|int|float|bool
    {
        $digits = Type::of($value)->toStringOrNull();

        return $digits !== null && Text::of($digits)->isDigits() ? (int) $digits : $value;
    }
}
