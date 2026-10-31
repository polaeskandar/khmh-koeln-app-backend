<?php

declare(strict_types=1);

namespace App\Platform\Problem\Service;

use App\Platform\Problem\Model\Problem;
use App\Platform\Problem\Service\ProblemTranslator\ProblemTranslator;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Throwable;

final readonly class ProblemTranslation
{
    /**
     * @param iterable<ProblemTranslator<Throwable>> $translators
     */
    public function __construct(
        #[AutowireIterator(ProblemTranslator::TAG)]
        private iterable $translators,
    ) {
    }

    public function of(Throwable $exception): ?Problem
    {
        $chain = new ArrayCollection(self::outermostFirst($exception));

        $problem = $chain
            ->map($this->translated(...))
            ->filter(self::isProblem(...))
            ->first();

        return $problem instanceof Problem ? $problem : HttpStatusFallback::of($chain);
    }

    private function translated(Throwable $cause): ?Problem
    {
        foreach ($this->translators as $translator) {
            if ($translator->supports($cause)) {
                return $translator->translate($cause);
            }
        }

        return null;
    }

    private static function isProblem(?Problem $problem): bool
    {
        return $problem !== null;
    }

    /**
     * @return list<Throwable>
     */
    private static function outermostFirst(?Throwable $cause): array
    {
        return $cause === null ? [] : [$cause, ...self::outermostFirst($cause->getPrevious())];
    }
}
