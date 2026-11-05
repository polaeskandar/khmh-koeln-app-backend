<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Attendance\Application\Service\AttendanceErasures;
use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Learning\Application\Service\LearningErasures;
use App\Context\Meetings\Application\Service\MeetingErasures;
use App\Context\Organization\Application\Service\StructureLookup;
use App\Context\People\Application\Service\PersonErasures;
use App\Context\People\Domain\ValueObject\ErasablePerson;
use App\Context\People\Domain\ValueObject\ErasedElsewhere;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Context\Privacy\Domain\Entity\DataRequest;
use App\Platform\Privacy\Contract\AccountReferences;
use App\Workflow\Privacy\Model\AccountSweep;
use App\Workflow\Privacy\Model\ErasableSubject;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\ErasureOutcome;
use App\Workflow\Privacy\Model\Mentions;
use App\Workflow\Privacy\Model\NamedMember;
use App\Workflow\Privacy\Model\PersonSweep;
use App\Workflow\Privacy\Model\SubjectErasure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Uid\Uuid;

final readonly class EraseRequestSubjectWorkflow
{
    /**
     * @param iterable<AccountReferences> $references
     */
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        private AccessCheck $access,
        private RequestErasures $requests,
        private PersonErasures $people,
        private AccountErasures $accounts,
        private MeetingErasures $meetings,
        private AttendanceErasures $attendance,
        private LearningErasures $learning,
        private StructureLookup $structure,
        #[AutowireIterator(AccountReferences::TAG)]
        private iterable $references,
    ) {
    }

    public function __invoke(string $requestId, string $viewerId, ErasureChoice $choice): void
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $subject = $this->requests->whileErasable($requestId, function (DataRequest $request) use (
            $requestId,
            $viewerId,
            $choice,
        ): ErasableSubject {
            $subject = ErasableSubject::of($request, $this->people, $this->accounts, $viewerId);
            $notes = $this->clearedNotes($subject, $choice);
            $outcome = $this->erased($subject);
            $this->requests->complete($requestId, $viewerId, [...$outcome->counts(), 'notes' => $notes]);

            return $subject;
        });

        if ($subject->accountId !== null) {
            $this->sweepAccount($subject->accountId);
        }
    }

    private function clearedNotes(ErasableSubject $subject, ErasureChoice $choice): int
    {
        if (!$choice->clearNotes || $subject->personId === null) {
            return 0;
        }

        $person = $this->people->erasable($subject->personId);
        $mentions = Mentions::of($person, $this->meetings, $this->attendance, $this->learning, $this->structure);

        return Mentions::cleared($mentions, $this->meetings, $this->attendance, $this->learning);
    }

    private function erased(ErasableSubject $subject): ErasureOutcome
    {
        return SubjectErasure::of(
            $subject,
            $this->people,
            $this->accounts,
            $this->sweepPerson(...),
            $this->sweepAccount(...),
        );
    }

    private function sweepPerson(ErasablePerson $person): ErasedElsewhere
    {
        $member = NamedMember::of($person, $this->structure);

        return PersonSweep::of($member, $this->meetings, $this->attendance, $this->learning, $this->requests);
    }

    private function sweepAccount(Uuid $accountId): int
    {
        return AccountSweep::of($this->references, $accountId);
    }
}
