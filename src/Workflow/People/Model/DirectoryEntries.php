<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Organization\Domain\Entity\Service;
use App\Context\Organization\Domain\Entity\Stage;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class DirectoryEntries
{
    /**
     * @param ReadableCollection<int, Person> $people
     * @param ReadableCollection<int, Membership> $memberships
     * @param ReadableCollection<int, Service> $services
     * @param ReadableCollection<int, Stage> $stages
     *
     * @return list<DirectoryEntry>
     */
    public static function of(
        ReadableCollection $people,
        ReadableCollection $memberships,
        ReadableCollection $services,
        ReadableCollection $stages,
    ): array {
        $placements = Placements::of($services, $stages);

        return $people->map(static fn (Person $person): DirectoryEntry => DirectoryEntry::of(
            $person,
            $placements->groupsOf($memberships->filter(
                static fn (Membership $membership): bool => $membership->person() === $person,
            )),
        ))->getValues();
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<Uuid>
     */
    public static function serviceIdsOf(ReadableCollection $memberships): array
    {
        return $memberships->map(static fn (Membership $held): Uuid => $held->serviceId())->getValues();
    }

    /**
     * @param ReadableCollection<int, Membership> $memberships
     *
     * @return list<Uuid>
     */
    public static function stageIdsOf(ReadableCollection $memberships): array
    {
        $stageIds = Collections::mapPresent($memberships, static fn (Membership $held): ?Uuid => $held->stageId());

        return $stageIds->getValues();
    }
}
