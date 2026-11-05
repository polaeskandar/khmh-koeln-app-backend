<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\PersonView;

final readonly class PersonResponse
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $displayName,
        public string $status,
    ) {
    }

    public static function of(PersonView $person): self
    {
        return new self($person->id, $person->firstName, $person->lastName, $person->displayName, $person->status);
    }
}
