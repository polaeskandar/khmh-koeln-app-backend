<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Workflow\Organization\Model\ServiceReference;
use App\Workflow\Organization\Model\StageReference;

final readonly class PersonGroup
{
    public function __construct(
        public ServiceReference $service,
        public ?StageReference $stage,
    ) {
    }
}
