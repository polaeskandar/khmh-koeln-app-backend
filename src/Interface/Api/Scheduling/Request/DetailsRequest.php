<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use App\Workflow\Scheduling\Model\DetailsChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DetailsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'meetings.scheduleNameRequired', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 120, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $name = null,
        #[Assert\Length(max: 500, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $description = null,
        #[Assert\Length(max: 120, normalizer: 'trim', maxMessage: 'validation.tooLong')]
        public ?string $location = null,
    ) {
    }

    public function choice(): DetailsChoice
    {
        return new DetailsChoice($this->name, $this->description, $this->location);
    }
}
