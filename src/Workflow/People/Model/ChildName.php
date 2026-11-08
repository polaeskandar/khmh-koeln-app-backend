<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\Person;
use App\Library\Strings\Model\Initials;

final readonly class ChildName
{
    public function __construct(
        public string $id,
        public string $name,
        public string $fullName,
        public string $initials,
    ) {
    }

    public static function of(Person $child): self
    {
        $name = $child->name();
        $shown = $name->displayName ?? $name->firstName;

        return new self(
            id: $child->id()->toRfc4122(),
            name: $shown,
            fullName: $name->firstName . ' ' . $name->lastName,
            initials: Initials::of($shown, $name->lastName),
        );
    }
}
