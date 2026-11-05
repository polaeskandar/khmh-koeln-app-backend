<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class PersonFilter
{
    /**
     * @param list<string> $words
     */
    public function __construct(
        public ?PersonStatus $status,
        public ?Uuid $serviceId,
        public ?Uuid $stageId,
        public array $words,
        public LocalDate $today,
    ) {
    }
}
