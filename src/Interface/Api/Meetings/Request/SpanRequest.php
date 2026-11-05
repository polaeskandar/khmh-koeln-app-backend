<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\SpanChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SpanRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'validation.chooseDate')]
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $startsOn = null,
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $endsOn = null,
    ) {
    }

    public function choice(): SpanChoice
    {
        return new SpanChoice($this->startsOn, $this->endsOn);
    }
}
