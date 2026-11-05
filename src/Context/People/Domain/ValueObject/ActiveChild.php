<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\NotFound;

final readonly class ActiveChild
{
    public static function of(?Person $found): Person
    {
        $child = $found ?? throw NotFound::of('person');

        return $child->status() === PersonStatus::Active
            ? $child
            : throw InvalidInput::field('personId', 'accounts.childNotFound');
    }
}
