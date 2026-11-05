<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;

final readonly class Enrolment
{
    public function __construct(
        public Person $person,
        public ?Membership $membership,
    ) {
    }
}
