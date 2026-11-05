<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class TeamChoice
{
    public function __construct(
        public ResponsibleChoice $responsible,
        public HelperChoice $helpers,
    ) {
    }
}
