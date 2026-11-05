<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\RuleViolation;

final readonly class AlreadyIn
{
    public static function of(Person $person, string $placeName): RuleViolation
    {
        return new RuleViolation('people.alreadyIn', ['name' => $person->name()->fullName(), 'where' => $placeName]);
    }
}
