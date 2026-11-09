<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Library\Problem\Exception\Forbidden;
use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @implements ProblemTranslator<Forbidden>
 */
final readonly class ForbiddenTranslator implements ProblemTranslator
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof Forbidden;
    }

    /**
     * @param Forbidden $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(
            Response::HTTP_FORBIDDEN,
            $exception->getMessage(),
            params: new ArrayCollection($exception->params),
        );
    }
}
