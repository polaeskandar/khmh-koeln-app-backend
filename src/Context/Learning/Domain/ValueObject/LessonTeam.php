<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class LessonTeam
{
    /**
     * @param list<Uuid> $helpers
     */
    public function __construct(
        public Uuid $responsible,
        public array $helpers,
    ) {
    }
}
