<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Library\Collections\Model\Collections;

final readonly class NamedMember
{
    public function __construct(
        public ErasablePerson $person,
        public ?string $group,
    ) {
    }

    public static function of(ErasablePerson $person, StructureLookup $structure): self
    {
        $last = Collections::sortedDescendingBy($person->memberships, keyOf: self::startKey(...))[0] ?? null;

        return new self($person, $last === null ? null : self::groupOf($last, $structure));
    }

    private static function groupOf(Membership $membership, StructureLookup $structure): ?string
    {
        $stageId = $membership->stageId();
        $stages = $stageId === null ? [] : $structure->stages([$stageId])->getValues();
        $services = $structure->services([$membership->serviceId()])->getValues();

        return ($stages[0] ?? null)?->name()->value ?? ($services[0] ?? null)?->name()->value;
    }

    private static function startKey(Membership $membership): string
    {
        return $membership->tenure()->startsOn->toString() . ' ' . $membership->id()->toRfc4122();
    }
}
