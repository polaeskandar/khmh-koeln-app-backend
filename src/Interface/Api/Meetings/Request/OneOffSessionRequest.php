<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Workflow\Meetings\Model\OneOffChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class OneOffSessionRequest
{
    public function __construct(
        #[Assert\Valid]
        public RosterRequest $roster = new RosterRequest(),
        #[Assert\NotBlank(message: 'meetings.titleRequired', normalizer: 'trim')]
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $title = null,
        #[Assert\Length(max: 120, maxMessage: 'validation.tooLong', normalizer: 'trim')]
        public ?string $location = null,
        #[Assert\NotBlank(message: 'validation.chooseDate')]
        public ?string $startsAt = null,
        public ?string $endsAt = null,
    ) {
    }

    public function oneOff(): OneOffChoice
    {
        return new OneOffChoice($this->roster->choice(), $this->title, $this->location, $this->startsAt, $this->endsAt);
    }
}
