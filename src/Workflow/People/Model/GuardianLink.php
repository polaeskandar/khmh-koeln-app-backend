<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Library\Strings\Model\Text;

final readonly class GuardianLink
{
    public function __construct(
        public string $personId,
        public string $name,
        public string $relationship,
    ) {
    }

    public static function of(GuardianRelationship $link): self
    {
        $child = $link->child();

        return new self(
            personId: $child->id()->toRfc4122(),
            name: Text::format('%s %s', $child->name()->firstName, $child->name()->lastName)->toString(),
            relationship: $link->relationship()->value,
        );
    }
}
