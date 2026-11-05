<?php

declare(strict_types=1);

namespace App\Workflow\Identity;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\MemberAccounts;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Context\People\Domain\Entity\Person;
use App\Workflow\Identity\Model\MemberCandidate;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\DirectoryEntry;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ListMemberCandidatesWorkflow
{
    private const int LIMIT = 20;

    public function __construct(
        private AccessCheck $access,
        private PersonDirectory $directory,
        private StructureLookup $structure,
        private MemberAccounts $members,
    ) {
    }

    /**
     * @return list<MemberCandidate>
     */
    public function __invoke(string $text): array
    {
        $this->access->require(Action::Read, Resource::Users, refusal: 'accounts.adminOnly');

        $people = $this->directory->search($text, self::LIMIT);
        $ids = $people->map(static fn (Person $person): Uuid => $person->id())->getValues();
        $linked = new ArrayCollection($this->members->linkedAmong($ids));

        $memberships = $this->directory->currentMembershipsOf($people);

        $entries = DirectoryEntries::of(
            $people,
            $memberships,
            $this->structure->services(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->stages(DirectoryEntries::stageIdsOf($memberships)),
        );

        return new ArrayCollection($entries)->map(
            static fn (DirectoryEntry $entry): MemberCandidate => new MemberCandidate(
                $entry,
                $linked->contains($entry->id),
            ),
        )->getValues();
    }
}
