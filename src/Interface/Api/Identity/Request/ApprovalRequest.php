<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use App\Workflow\Identity\Model\ApprovalChoice;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApprovalRequest
{
    /**
     * @param list<string> $childIds
     * @param list<string> $roleIds
     */
    public function __construct(
        #[Assert\NotBlank(message: 'validation.invalidChoice')]
        #[Assert\Choice(choices: ['parent', 'servant'], message: 'validation.invalidChoice')]
        public ?string $as = null,
        #[Assert\All([new Assert\Type(type: 'string', message: 'accounts.childNotFound')])]
        public array $childIds = [],
        #[Assert\All([new Assert\Type(type: 'string', message: 'roles.unknown')])]
        public array $roleIds = [],
    ) {
    }

    public function choice(): ApprovalChoice
    {
        return new ApprovalChoice($this->as ?? '', $this->roleIds, $this->childIds);
    }
}
