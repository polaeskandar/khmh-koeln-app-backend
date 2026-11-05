<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Request;

use App\Workflow\People\Model\NameChanges;

final readonly class RenamePersonRequest
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $displayName = null,
    ) {
    }

    public function changes(): NameChanges
    {
        return new NameChanges($this->firstName, $this->lastName, $this->displayName);
    }
}
