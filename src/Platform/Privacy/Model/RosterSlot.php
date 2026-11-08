<?php

declare(strict_types=1);

namespace App\Platform\Privacy\Model;

use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class RosterSlot
{
    public function __construct(
        public string $id,
        public Uuid $serviceId,
        public ?Uuid $stageId,
        public LocalDate $date,
    ) {
    }
}
