<?php

declare(strict_types=1);

namespace App\Interface\Api\Meetings\Request;

use App\Context\Meetings\Domain\Enum\RosterMode;
use App\Workflow\Meetings\Model\RosterChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RosterRequest
{
    public function __construct(
        public ?string $serviceId = null,
        public ?string $stageId = null,
        #[Assert\NotBlank(message: 'meetings.chooseRosterMode')]
        #[Assert\Choice(callback: [RosterMode::class, 'values'], message: 'meetings.chooseRosterMode')]
        public ?string $mode = null,
    ) {
    }

    public function choice(): RosterChoice
    {
        return new RosterChoice($this->serviceId, $this->stageId, $this->mode);
    }
}
