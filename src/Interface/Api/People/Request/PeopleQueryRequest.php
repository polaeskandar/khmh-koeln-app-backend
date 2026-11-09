<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Workflow\People\Model\PeopleFilter;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PeopleQueryRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['active', 'inactive', 'all'], message: 'validation.invalidChoice')]
        public string $status = 'active',
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $serviceId = null,
        #[Assert\NotBlank(message: 'validation.invalidType', allowNull: true)]
        #[Assert\Uuid(message: 'validation.invalidType')]
        public ?string $stageId = null,
        #[Assert\Length(max: 100, maxMessage: 'validation.tooLong', charsetMessage: 'validation.invalidType')]
        public string $q = '',
        public int $page = 1,
    ) {
    }

    public function filter(): PeopleFilter
    {
        return new PeopleFilter($this->status, $this->serviceId, $this->stageId, $this->q, $this->page);
    }
}
