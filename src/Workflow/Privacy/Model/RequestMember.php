<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

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

    /**
     * @param list<MembershipRow> $memberships
     */
    public static function of(?Person $person, array $memberships): ?self
    {
        return $person === null ? null : new self(PersonReference::of($person), $person->status()->value, $memberships);
    }
}
