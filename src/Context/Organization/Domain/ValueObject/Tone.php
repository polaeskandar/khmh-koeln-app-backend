<?php

declare(strict_types=1);

namespace App\Context\Organization\Domain\ValueObject;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Tone
{
    private const array TONES = [1, 2, 3, 4, 5, 6, 7, 8];

    private function __construct(
        #[ORM\Column(name: 'tone', type: Types::SMALLINT)]
        public int $value,
    ) {
    }

    /**
     * @param ReadableCollection<int, int> $tonesInUse
     */
    public static function leastUsed(ReadableCollection $tonesInUse): self
    {
        $usage = static fn (int $tone): int => $tonesInUse
            ->filter(static fn (int $used): bool => $used === $tone)
            ->count();

        return new self(new ArrayCollection(self::TONES)->reduce(static fn (int $fewest, int $tone): int => $usage(
            $tone,
        ) < $usage($fewest)
                ? $tone
                : $fewest, initial: 1));
    }
}
