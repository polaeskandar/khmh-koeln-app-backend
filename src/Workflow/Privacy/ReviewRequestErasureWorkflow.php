<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Privacy\Application\Service\RequestErasures;
use App\Context\Privacy\Domain\ValueObject\ErasableSubject;
use App\Platform\Doctrine\Contract\Transactions;
use App\Workflow\Privacy\Model\ErasureChoice;
use App\Workflow\Privacy\Model\ErasureReview;

final readonly class ReviewRequestErasureWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private Transactions $transactions,
        private RequestErasures $requests,
        private ListMentionsWorkflow $listMentions,
        private EraseSubjectWorkflow $eraseSubject,
    ) {
    }

    public function __invoke(string $requestId, string $viewerId): ErasureReview
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        return $this->transactions->rehearse(fn (): ErasureReview => $this->requests->whileErasable(
            $requestId,
            $viewerId,
            $this->reviewOf(...),
        ));
    }

    private function reviewOf(ErasableSubject $subject): ErasureReview
    {
        $mentions = $subject->personId === null ? [] : ($this->listMentions)($subject->personId);

        return ErasureReview::of(($this->eraseSubject)($subject, new ErasureChoice(clearNotes: false)), $mentions);
    }
}
