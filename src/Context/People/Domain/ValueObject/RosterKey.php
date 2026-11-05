<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Library\Strings\Model\Text;
use App\Library\Time\Model\LocalDate;
use Symfony\Component\Uid\Uuid;

final readonly class RosterKey
{
    public function __construct(
        public Uuid $serviceId,
        public ?Uuid $stageId,
        public LocalDate $day,
    ) {
    }

    public function id(): string
    {
        return Text::format(
            '%s/%s/%s',
            $this->serviceId->toRfc4122(),
            $this->stageId?->toRfc4122() ?? '-',
            $this->day->toString(),
        )->toString();
    }
}
