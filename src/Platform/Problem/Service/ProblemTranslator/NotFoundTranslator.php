<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Library\Problem\Exception\NotFound;
use App\Platform\Problem\Model\Problem;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @implements ProblemTranslatorInterface<NotFound>
 */
final readonly class NotFoundTranslator implements ProblemTranslatorInterface
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof NotFound;
    }

    /**
     * @param NotFound $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(Response::HTTP_NOT_FOUND, $exception->getMessage());
    }
}
