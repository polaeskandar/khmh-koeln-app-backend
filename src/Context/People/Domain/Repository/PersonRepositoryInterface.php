<?php

declare(strict_types=1);

namespace App\Context\People\Domain\Repository;

use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;

interface PersonRepositoryInterface
{
    public function save(Person $person, Membership ...$memberships): void;
}
