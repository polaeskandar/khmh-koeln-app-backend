<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Context\People\Domain\Entity\Membership;
use App\Context\People\Domain\Entity\Person;
use App\Library\Collections\Model\Collections;
use App\Workflow\People\Model\DirectoryEntry;
use App\Workflow\People\Model\Placements;

final readonly class SearchDirectoryWorkflow
{
    private const int LIMIT = 20;

    public function __construct(
        private AccessCheck $access,
        private PersonDirectory $directory,
        private StructureLookup $structure,
    ) {
    }

    /**
     * @return list<DirectoryEntry>
     */
    public function __invoke(string $text): array
    {
        $this->access->require(Action::Read, Resource::Members);

        $people = $this->directory->search($text, self::LIMIT);
        $memberships = $this->directory->currentMembershipsOf($people);

        $placements = Placements::of(
            $this->structure->services(
                $memberships->map(static fn (Membership $held) => $held->serviceId())->getValues(),
            ),
            $this->structure->stages(
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
