<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Model\Problem;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * @implements ProblemTranslatorInterface<UnauthorizedHttpException>
 */
final readonly class UnauthorizedTranslator implements ProblemTranslatorInterface
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof UnauthorizedHttpException;
    }

    /**
     * @param UnauthorizedHttpException $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        $message = $exception->getMessage();

        return new Problem(Response::HTTP_UNAUTHORIZED, Problem::isCode($message) ? $message : 'auth.unauthenticated');
    }
}
