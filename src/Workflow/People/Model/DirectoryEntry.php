<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Person;

final readonly class DirectoryEntry
{
    /**
     * @param list<PersonGroup> $groups
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $displayName,
        public array $groups,
    ) {
    }

    /**
     * @param list<PersonGroup> $groups
     */
    public static function of(Person $person, array $groups): self
    {
        $name = $person->name();

        return new self($person->id()->toRfc4122(), $name->firstName, $name->lastName, $name->displayName, $groups);
    }
}
