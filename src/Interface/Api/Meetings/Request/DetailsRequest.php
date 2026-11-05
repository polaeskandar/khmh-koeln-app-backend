<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\DetailsChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class DetailsRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'meetings.scheduleNameRequired', allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $name = null,
        #[Assert\Length(max: 500, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $description = null,
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $location = null,
    ) {
    }

    public function choice(): DetailsChoice
    {
        return new DetailsChoice($this->name, $this->description, $this->location);
    }
}
