<?php

declare(strict_types=1);

namespace App\Workflow\People;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Organization\Application\Service\PlacementChoices;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonDirectory;
use App\Context\People\Application\Service\PersonWrites;
use App\Context\People\Domain\Entity\Person;
use App\Context\People\Domain\Enum\PersonStatus;
use App\Context\People\Domain\ValueObject\PersonName;
use App\Context\People\Domain\ValueObject\Placement;
use App\Library\Problem\Exception\InvalidInput;
use App\Library\Problem\Exception\RuleViolation;
use App\Library\Strings\Model\Text;
use App\Workflow\Organization\Model\StructureGroup;
use App\Workflow\People\Model\CreatedPerson;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\DirectoryEntry;
use App\Workflow\People\Model\PersonDraft;
use App\Workflow\People\Model\PlacementChoice;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class CreatePersonWorkflow
{
    private const string PLACEMENT = 'placement.';

    public function __construct(
        private AccessCheck $access,
        private PersonWrites $records,
        private PlacementChoices $placements,
        private PersonDirectory $directory,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(PersonDraft $draft): CreatedPerson
    {
        $this->access->require(Action::Create, Resource::Members, refusal: 'people.addNotAllowed');

        InvalidInput::all(
            fn (): PersonName => $this->records->buildName($draft->firstName, $draft->lastName, $draft->displayName),
            fn (): ?Placement => $this->placementOf($draft->placement),
        );

        $name = $this->records->buildName($draft->firstName, $draft->lastName, $draft->displayName);
        $placement = $this->placementOf($draft->placement);

        if (!$draft->confirmDuplicate) {
            $this->refuseNamesakesOf($name);
        }

        return CreatedPerson::of($this->records->create($name, $placement));
    }

    private function placementOf(?PlacementChoice $choice): ?Placement
    {
        if ($choice === null) {
            $this->access->requireChosen(Action::Create, Resource::Members, null, fieldPrefix: self::PLACEMENT);

            return null;
        }

        $chosen = $this->placements->chosen($choice->serviceId, $choice->stageId, fieldPrefix: self::PLACEMENT);

        $this->access->requireChosen(
            Action::Create,
            Resource::Members,
            StructureGroup::ofPlacement($chosen),
            fieldPrefix: self::PLACEMENT,
        );

        return new Placement($chosen->service->id(), $chosen->stage?->id());
    }

    private function refuseNamesakesOf(PersonName $name): void
    {
        $namesakes = $this->records->listNamesakesOf($name);

        if ($namesakes->isEmpty()) {
            return;
        }

        $mayRead = $this->access->isAllowed(Action::Read, Resource::Members);
        $shown = $namesakes
            ->filter(static fn (Person $person): bool => $person->status() === PersonStatus::Active)
            ->filter(static fn (): bool => $mayRead);

        throw new RuleViolation(
            'people.duplicate',
            [
                'count' => $namesakes->count(),
                'name' => Text::format('%s %s', $name->firstName, $name->lastName)->toString(),
            ],
            ['candidates' => $this->entriesOf($shown)],
        );
    }

    /**
     * @param ReadableCollection<int, Person> $people
     *
     * @return list<DirectoryEntry>
     */
    private function entriesOf(ReadableCollection $people): array
    {
        $memberships = $this->directory->listCurrentMembershipsOf($people);

        return DirectoryEntries::of(
            $people,
            $memberships,
            $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
        );
    }
}
