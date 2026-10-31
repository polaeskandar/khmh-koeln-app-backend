<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Library\Problem\Exception\InvalidInput;
use App\Platform\Problem\Model\FieldError;
use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @implements ProblemTranslator<InvalidInput>
 */
final readonly class InvalidInputTranslator implements ProblemTranslator
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof InvalidInput;
    }

    /**
     * @param InvalidInput $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'checkFields',
            fieldErrors: new ArrayCollection($exception->fieldErrors)->map(self::fieldErrorOf(...)),
        );
    }

    /**
     * @param array{code: string, params?: array<string, string|int|float|bool|null>} $error
     */
    private static function fieldErrorOf(array $error): FieldError
    {
        return new FieldError(code: $error['code'], params: new ArrayCollection($error['params'] ?? []));
    }
}
