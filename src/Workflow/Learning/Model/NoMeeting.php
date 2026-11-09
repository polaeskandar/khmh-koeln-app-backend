<?php

declare(strict_types=1);

namespace App\Workflow\Learning\Model;

use App\Library\Time\Model\LocalDate;

final readonly class NoMeeting
{
    public function __construct(
        public string $date,
    ) {
    }

    public static function on(LocalDate $date): self
    {
        return new self($date->toString());
    }
}
