<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Library\Collections\Model\Collections;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StageSequence
{
    public const int MAX_STAGES = 200;

    /**
     * @param list<string> $stageIds
     */
    private function __construct(
        public array $stageIds,
    ) {
    }

    /**
     * @param list<string> $stageIds
     */
    public static function of(array $stageIds): self
    {
        if (new ArrayCollection($stageIds)->count() > self::MAX_STAGES) {
            throw InvalidInput::field('stageIds', 'validation.tooMany', ['max' => self::MAX_STAGES]);
        }

        return new self($stageIds);
    }

    /**
     * @param list<string> $activeIds
     */
    public function ensureCovers(array $activeIds): void
    {
        if (self::sorted($activeIds) !== self::sorted($this->stageIds)) {
            throw new RuleViolation('stages.reorderStale');
        }
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function sorted(array $ids): array
    {
        return Collections::sortedBy(new ArrayCollection($ids), keyOf: static fn (string $id): string => $id);
    }
}
