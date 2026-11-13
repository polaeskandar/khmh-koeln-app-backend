<?php

declare(strict_types=1);

namespace App\Interface\Api\Scheduling\Request;

use App\Workflow\Scheduling\Model\AgendaChoice;
use App\Workflow\Scheduling\Model\AgendaFilter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class AgendaQueryRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidDate', allowNull: true)]
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $from = null,
        #[Assert\NotBlank(message: 'validation.invalidDate', allowNull: true)]
        #[Assert\Date(message: 'validation.invalidDate')]
        public ?string $to = null,
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $serviceId = null,
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $stageId = null,
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $scheduleId = null,
    ) {
    }

    public function filter(): AgendaFilter
    {
        return new AgendaFilter(
            $this->from,
            $this->to,
            new AgendaChoice($this->serviceId, $this->stageId, $this->scheduleId),
        );
    }
}
