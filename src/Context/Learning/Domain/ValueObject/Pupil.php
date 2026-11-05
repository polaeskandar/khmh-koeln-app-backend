<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class Pupil
{
    /**
     * @param list<Uuid> $stageIds
     */
    public function __construct(
        public Uuid $personId,
        public array $stageIds,
    ) {
    }
}
