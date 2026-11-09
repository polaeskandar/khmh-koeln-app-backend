<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\RuleViolation;

final readonly class AlreadyInService
{
    public static function of(Person $person, string $rosterName): RuleViolation
    {
        return new RuleViolation('people.alreadyInService', [
            'name' => $person->name()->fullName(),
            'where' => $rosterName,
        ]);
    }
}
