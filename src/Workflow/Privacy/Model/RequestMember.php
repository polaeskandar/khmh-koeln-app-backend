<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PeopleExports;
use App\Context\People\Domain\Entity\Person;
use App\Workflow\People\Model\PersonReference;

final readonly class RequestMember
{
    /**
     * @param list<MembershipRow> $memberships
     */
    public function __construct(
        public PersonReference $person,
        public string $status,
        public array $memberships,
    ) {
    }

    public static function of(?Person $person, PeopleExports $people, StructureLookup $structure): ?self
    {
        if ($person === null) {
            return null;
        }

        return new self(
            PersonReference::of($person),
            $person->status()->value,
            MembershipRow::listOf($people->membershipsOf($person), $structure),
        );
    }
}
