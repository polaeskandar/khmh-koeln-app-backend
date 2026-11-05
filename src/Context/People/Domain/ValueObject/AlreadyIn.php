<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Person;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;

final readonly class AlreadyIn
{
    public static function of(Person $person, string $placeName): RuleViolation
    {
        $name = $person->name();

        return new RuleViolation('people.alreadyIn', [
            'name' => Text::format('%s %s', $name->firstName, $name->lastName)->toString(),
            'where' => $placeName,
        ]);
    }
}
