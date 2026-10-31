<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Model\Problem;
use App\Platform\Problem\Service\ViolationFieldErrors;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * @implements ProblemTranslator<ValidationFailedException>
 */
final readonly class ValidationFailedTranslator implements ProblemTranslator
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof ValidationFailedException;
    }

    /**
     * @param ValidationFailedException $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'checkFields',
            fieldErrors: ViolationFieldErrors::of($exception->getViolations()),
        );
    }
}
