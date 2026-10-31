<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use App\Platform\Problem\Model\FieldError;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final readonly class ViolationFieldErrors
{
    /**
     * @return ReadableCollection<string, FieldError>
     */
    public static function of(ConstraintViolationListInterface $violations): ReadableCollection
    {
        return $violations |> Collections::of(...) |> self::firstPerField(...) |> self::fieldErrors(...);
    }

    /**
     * @param ReadableCollection<int, ConstraintViolationInterface> $violations
     *
     * @return ReadableCollection<string, ConstraintViolationInterface>
     */
    private static function firstPerField(ReadableCollection $violations): ReadableCollection
    {
        return Collections::firstPerKey($violations, keyOf: self::fieldOf(...));
    }

    /**
     * @param ReadableCollection<string, ConstraintViolationInterface> $violations
     *
     * @return ReadableCollection<string, FieldError>
     */
    private static function fieldErrors(ReadableCollection $violations): ReadableCollection
    {
        return $violations->map(self::errorOf(...));
    }

    private static function fieldOf(ConstraintViolationInterface $violation): string
    {
        return Text::of($violation->getPropertyPath())->replaceMatches('/\[(\d+)]/', '.$1')->toString();
    }

    private static function errorOf(ConstraintViolationInterface $violation): FieldError
    {
        return new FieldError(code: ViolationCode::of($violation), params: ViolationParams::of($violation));
    }
}
