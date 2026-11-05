<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service\ProblemTranslator;

use App\Platform\Problem\Model\Problem;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use LogicException;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @implements ProblemTranslator<Throwable>
 */
final readonly class FixedProblemTranslator implements ProblemTranslator
{
    private const array PROBLEMS = [
        UniqueConstraintViolationException::class => [Response::HTTP_CONFLICT, 'conflict'],
        OptimisticLockException::class => [Response::HTTP_CONFLICT, 'conflict'],
    ];

    #[Override]
    public function supports(Throwable $exception): bool
    {
        return $this->problemFor($exception) !== null;
    }

    #[Override]
    public function translate(Throwable $exception): Problem
    {
        $problem = $this->problemFor($exception);

        if ($problem === null) {
            throw new LogicException('translate() is only called for an exception that supports() accepted.');
        }

        return $problem;
    }

    private function problemFor(Throwable $exception): ?Problem
    {
        $problem = new ArrayCollection(self::PROBLEMS)->findFirst(
            static fn (string $class): bool => $exception instanceof $class,
        );

        return $problem === null ? null : new Problem($problem[0], $problem[1]);
    }
}
