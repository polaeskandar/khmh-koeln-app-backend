<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Library\Problem\Model\Ids;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\ErasedGroup;
use App\Workflow\Privacy\Model\PersonErasureCounts;

final readonly class ErasePersonWorkflow
{
    public function __construct(
        private PersonErasures $people,
        private StructureLookup $structure,
        private Transactions $transactions,
    ) {
    }

    public function __invoke(string $personId): PersonErasureCounts
    {
        return $this->transactions->run(fn (): PersonErasureCounts => PersonErasureCounts::of($this->people->erase(
            Ids::toUuid($personId, notFound: 'person'),
            $this->groupOf(...),
        )));
    }

    private function groupOf(ErasablePerson $person): ?string
    {
        $memberships = $person->memberships;

        return ErasedGroup::of($memberships, Placements::of(
            $this->structure->listServices(DirectoryEntries::serviceIdsOf($memberships)),
            $this->structure->listStages(DirectoryEntries::stageIdsOf($memberships)),
        ));
    }
}
