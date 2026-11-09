<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Library\Collections\Model\Collections;
use App\Platform\Problem\Model\FieldError;
use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Throwable;

/**
 * @implements ProblemTranslatorInterface<ExtraAttributesException>
 */
final readonly class ExtraAttributesTranslator implements ProblemTranslatorInterface
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ExtraAttributesException;
    }

    /**
     * @param ExtraAttributesException $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'checkFields',
            fieldErrors: $exception->getExtraAttributes() |> self::byName(...) |> self::asUnexpected(...),
        );
    }

    /**
     * @param list<string> $attributes
     *
     * @return ReadableCollection<string, string>
     */
    private static function byName(array $attributes): ReadableCollection
    {
        return Collections::firstPerKey(
            new ArrayCollection($attributes),
            keyOf: static fn (string $name): string => $name,
        );
    }

    /**
     * @param ReadableCollection<string, string> $attributes
     *
     * @return ReadableCollection<string, FieldError>
     */
    private static function asUnexpected(ReadableCollection $attributes): ReadableCollection
    {
        return $attributes->map(static fn (): FieldError => new FieldError('validation.unexpectedField'));
    }
}
