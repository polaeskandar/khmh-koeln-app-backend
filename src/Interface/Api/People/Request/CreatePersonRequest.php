<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Workflow\People\Model\PersonDraft;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePersonRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'people.firstNameRequired', normalizer: 'trim')]
        public ?string $firstName = null,
        #[Assert\NotBlank(message: 'people.lastNameRequired', normalizer: 'trim')]
        public ?string $lastName = null,
        public ?string $displayName = null,
        public ?PlacementRequest $placement = null,
        public bool $confirmDuplicate = false,
    ) {
    }

    public function draft(): PersonDraft
    {
        return new PersonDraft(
            $this->firstName,
            $this->lastName,
            $this->displayName,
            $this->placement?->choice(),
            $this->confirmDuplicate,
        );
    }
}
