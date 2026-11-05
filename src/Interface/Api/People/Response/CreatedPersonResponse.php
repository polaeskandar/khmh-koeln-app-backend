<?php

declare(strict_types=1);

namespace App\Interface\Api\People\Response;

use App\Workflow\People\Model\CreatedPerson;
use App\Workflow\People\Model\MembershipView;
use App\Workflow\People\Model\PersonView;

final readonly class CreatedPersonResponse
{
    public function __construct(
        public PersonView $person,
        public ?MembershipView $membership,
    ) {
    }

    public static function of(CreatedPerson $created): self
    {
        return new self($created->person, $created->membership);
    }
}
