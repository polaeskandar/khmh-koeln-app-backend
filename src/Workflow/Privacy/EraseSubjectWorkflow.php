<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccountErasures;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\ErasureOutcome;

final readonly class EraseSubjectWorkflow
{
    public function __construct(
        private ClearMentionsWorkflow $clearMentions,
        private ErasePersonWorkflow $erasePerson,
        private EraseAccountWorkflow $eraseAccount,
        private AccountErasures $accounts,
    ) {
    }

    public function __invoke(ErasableSubject $asked, ErasureChoice $choice): ErasureOutcome
    {
        $subject = $asked->joined(
            $this->accounts->recordOfAccount($asked->accountId),
            $this->accounts->accountOfRecord($asked->personId),
        );

        $notes = ($this->clearMentions)($subject->personId, $choice);
        $personId = $subject->personId?->toRfc4122();
        $accountId = $subject->accountId?->toRfc4122();

        return new ErasureOutcome(
            $notes,
            $personId === null ? null : ($this->erasePerson)($personId),
            $accountId === null ? null : ($this->eraseAccount)($accountId),
            $subject->accountId,
        );
    }
}
