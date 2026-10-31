<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Library\Problem\Exception\RuleViolation;
use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @implements ProblemTranslator<RuleViolation>
 */
final readonly class RuleViolationTranslator implements ProblemTranslator
{
    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof RuleViolation;
    }

    /**
     * @param RuleViolation $exception
     */
    #[Override]
    public function translate(Throwable $exception): Problem
    {
        return new Problem(
            Response::HTTP_CONFLICT,
            $exception->getMessage(),
            params: new ArrayCollection($exception->params),
            extensions: new ArrayCollection($exception->extensions),
        );
    }
}
