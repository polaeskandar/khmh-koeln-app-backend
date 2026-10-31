<?php

declare(strict_types=1);

namespace App\Tests\Support\Platform\Problem;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ProbeSchedule
{
    public function __construct(
        #[Assert\NotBlank(message: 'validation.chooseDate')]
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $date = null,
        #[Assert\NotBlank(message: 'validation.chooseTime')]
        #[Assert\Time(message: 'validation.invalidTime', withSeconds: false)]
        public ?string $time = null,
    ) {
    }
}
