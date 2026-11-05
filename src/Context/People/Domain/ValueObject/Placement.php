<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class Placement
{
    public function __construct(
        public Uuid $serviceId,
        public ?Uuid $stageId,
    ) {
    }
}
