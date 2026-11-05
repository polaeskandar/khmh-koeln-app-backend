<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GiveAccountRoleRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'roles.chooseRole')]
        #[Assert\Uuid(message: 'roles.unknown')]
        public ?string $roleId = null,
    ) {
    }
}
