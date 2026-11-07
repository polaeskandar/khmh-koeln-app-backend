<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\TimesChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TimesRequest
{
    public function __construct(
        #[Assert\Time(message: 'validation.invalidTime', withSeconds: false)]
        public ?string $startTime = null,
        #[Assert\Time(message: 'validation.invalidTime', withSeconds: false)]
        public ?string $endTime = null,
    ) {
    }

    public function choice(): TimesChoice
    {
        return new TimesChoice($this->startTime, $this->endTime);
    }
}
