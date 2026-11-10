<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\Privacy\Application\Service\ErasureRuns;
use App\Context\Privacy\Application\Service\ScheduledErasures;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\DueErasures;
use App\Workflow\Privacy\Model\ErasedGroup;
use App\Workflow\Privacy\Model\ErasureCounts;
use App\Workflow\Privacy\Model\ErasureOutcome;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class EraseDueRequestsWorkflow
{
    public function __construct(
        private ErasureRuns $runs,
        private ScheduledErasures $scheduled,
        private PersonErasures $people,
        private AccountErasures $accounts,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(): DueErasures
    {
        return DueErasures::of($this->runs->eachDue($this->erase(...)));
    }

    private function erase(Uuid $requestId): bool
    {
        $outcome = $this->scheduled->whileDue($requestId, fn (ErasableSubject $asked): ErasureOutcome => $this->erased(
            $requestId,
            $asked,
        ));

        if ($outcome?->accountId !== null) {
            $this->accounts->sweep($outcome->accountId);
        }

        return $outcome !== null;
    }

    private function erased(Uuid $requestId, ErasableSubject $asked): ErasureOutcome
    {
        $subject = $asked->joined(
            $this->accounts->recordOfAccount($asked->accountId),
            $this->accounts->findAccountOfRecord($asked->personId),
        );

        $outcome = new ErasureOutcome(
            $subject->personId === null
                ? 0
                : $this->people->clearMentions($this->people->listMentionsOf($subject->personId)),
            $this->personCounts($subject->personId),
            $this->accountCounts($subject->accountId),
            $subject->accountId,
            mentions: [],
        );

        $this->scheduled->complete($requestId, $outcome->counts());

        return $outcome;
    }

    private function personCounts(?Uuid $personId): ?PersonErasureCounts
    {
        if ($personId === null) {
            return null;
        }

        return PersonErasureCounts::of($this->people->erase($personId, $this->groupOf(...)));
    }

    private function accountCounts(?Uuid $accountId): ?ErasureCounts
    {
        if ($accountId === null) {
            return null;
        }

        $guardianLinks = $this->people->countGuardianLinksOf($accountId);

        return ErasureCounts::of($this->accounts->erase($accountId), $guardianLinks);
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
