<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Model;

use Symfony\Component\Uid\Uuid;

final readonly class ErasedPlace
{
    public function __construct(
        public Uuid $serviceId,
        public ?Uuid $stageId,
    ) {
    }
}
