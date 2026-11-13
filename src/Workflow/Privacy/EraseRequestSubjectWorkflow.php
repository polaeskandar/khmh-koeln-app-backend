<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Access\Application\Service\AccessCheck;
use App\Context\Access\Domain\Enum\Action;
use App\Context\Access\Domain\Enum\Resource;
use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Library\Problem\Model\Ids;
use App\Workflow\People\Model\DirectoryEntries;
use App\Workflow\People\Model\Placements;
use App\Workflow\Privacy\Model\ErasedGroup;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\ErasureCounts;
use App\Workflow\Privacy\Model\ErasureOutcome;
use App\Workflow\Privacy\Model\ErasureReview;
use App\Workflow\Privacy\Model\Mentions;
use App\Workflow\Privacy\Model\PersonErasureCounts;
use Symfony\Component\Uid\Uuid;

final readonly class EraseRequestSubjectWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private RequestErasures $requests,
        private PersonErasures $people,
        private AccountErasures $accounts,
        private StructureLookup $structure,
    ) {
    }

    public function __invoke(string $requestId, string $viewerId, ErasureChoice $choice): ErasureReview
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $id = Ids::toUuid($requestId, notFound: 'request');
        $viewer = Uuid::fromString($viewerId);

        $outcome = $this->requests->whileErasable(
            $id,
            $viewer,
            fn (ErasableSubject $asked): ErasureOutcome => $this->erased($asked, $choice, $id, $viewer),
        );

        if ($outcome->accountId !== null) {
            $this->accounts->sweep($outcome->accountId);
        }

        return ErasureReview::of($outcome);
    }

    private function erased(
        ErasableSubject $asked,
        ErasureChoice $choice,
        Uuid $requestId,
        Uuid $viewerId,
    ): ErasureOutcome {
        $subject = $asked->joined(
            $this->accounts->recordOfAccount($asked->accountId),
            $this->accounts->findAccountOfRecord($asked->personId),
        );

        $found = $subject->personId === null ? [] : $this->people->listMentionsOf($subject->personId);

        $outcome = new ErasureOutcome(
            $this->people->clearMentions($choice->notesToClear($found)),
            $this->personCounts($subject->personId),
            $this->accountCounts($subject->accountId),
            $subject->accountId,
            Mentions::of($found, $this->structure->listStages(Mentions::stageIdsOf($found))),
        );

        $this->requests->complete($requestId, $viewerId, $outcome->counts());

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
