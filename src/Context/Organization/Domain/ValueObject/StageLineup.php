<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use App\Context\Organization\Domain\Entity\Stage;
use App\Library\Collections\Model\Collections;
use App\Library\Strings\Model\Text;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class StageLineup
{
    /**
     * @param list<Stage> $active
     * @param list<Stage> $archived
     */
    private function __construct(
        private array $active,
        private array $archived,
    ) {
    }

    /**
     * @param ReadableCollection<int, Stage> $stages
     */
    public static function besides(Stage $stage, ReadableCollection $stages): self
    {
        $others = $stages->filter(static fn (Stage $other): bool => $other !== $stage);

        return new self(
            Collections::sortedBy(
                $others->filter(static fn (Stage $other): bool => !$other->isArchived()),
                keyOf: self::positionKey(...),
            ),
            Collections::sortedBy(
                $others->filter(static fn (Stage $other): bool => $other->isArchived()),
                keyOf: self::positionKey(...),
            ),
        );
    }

    /**
     * @return list<Stage>
     */
    public function others(): array
    {
        return new ArrayCollection([...$this->active, ...$this->archived])->getValues();
    }

    /**
     * @return list<Stage>
     */
    public function placing(Stage $stage): array
    {
        return new ArrayCollection([...$this->active, $stage, ...$this->archived])->getValues();
    }

    private static function positionKey(Stage $stage): string
    {
        return Text::format('%06d %s', $stage->position(), $stage->id()->toRfc4122())->toString();
    }
}
