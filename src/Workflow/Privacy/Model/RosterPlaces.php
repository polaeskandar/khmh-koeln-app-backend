<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Meetings\Domain\ValueObject\RosterPlace;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\ValueObject\ErasablePerson;

final readonly class RosterPlaces
{
    /**
     * @return list<RosterPlace>
     */
    public static function of(ErasablePerson $person): array
    {
        return $person
            ->memberships
            ->map(
                static fn (Membership $membership): RosterPlace => new RosterPlace(
                    $membership->serviceId(),
                    $membership->stageId(),
                ),
            )
            ->getValues();
    }
}
