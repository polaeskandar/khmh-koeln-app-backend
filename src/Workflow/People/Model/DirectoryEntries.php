<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class DirectoryEntries
{
    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return list<DirectoryEntry>
     */
    public static function lookedUpIn(
        PersonDirectory $directory,
        StructureLookup $structure,
        ReadableCollection $people,
    ): array {
        $memberships = $directory->currentMembershipsOf($people);

        $placements = Placements::of(
            $structure->services($memberships->map(static fn (Membership $held) => $held->serviceId())->getValues()),
            $structure->stages(
                Collections::mapPresent($memberships, static fn (Membership $held) => $held->stageId())->getValues(),
            ),
        );

        return $people->map(static fn (Person $person): DirectoryEntry => DirectoryEntry::of(
            $person,
            $placements->groupsOf($memberships->filter(
                static fn (Membership $membership): bool => $membership->person() === $person,
            )),
        ))->getValues();
    }
}
