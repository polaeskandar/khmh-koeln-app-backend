<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Workflow\People\Model\PeopleQuery;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PeopleQueryRequest
{
    public function __construct(
        public string $status = 'active',
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $serviceId = null,
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $stageId = null,
        public string $q = '',
        public int $page = 1,
    ) {
    }

    public function query(): PeopleQuery
    {
        return new PeopleQuery($this->status, $this->serviceId, $this->stageId, $this->q, $this->page);
    }
}
