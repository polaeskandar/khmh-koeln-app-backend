<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Model\Problem;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * @implements ProblemTranslator<NotFoundHttpException>
 */
final readonly class NotFoundHttpTranslator implements ProblemTranslator
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof NotFoundHttpException;
    }

    /**
     * @param NotFoundHttpException $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        $message = $exception->getMessage();

        return new Problem(Response::HTTP_NOT_FOUND, Problem::isCode($message) ? $message : 'notFound.route');
    }
}
