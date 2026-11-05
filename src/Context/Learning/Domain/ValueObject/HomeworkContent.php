<?php

declare(strict_types=1);

namespace App\Context\Learning\Domain\ValueObject;

final readonly class HomeworkContent
{
    public function __construct(
        public HomeworkText $text,
        public Materials $resources,
    ) {
    }
}
