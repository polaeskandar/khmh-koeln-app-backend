<?php

declare(strict_types=1);

namespace App\Workflow\Privacy;

use App\Context\Identity\Application\Service\AccessCheck;
use App\Context\Identity\Application\Service\AccountLookup;
use App\Context\Identity\Application\Service\DataCopyMails;
use App\Context\Identity\Domain\Enum\Action;
use App\Context\Identity\Domain\Enum\Resource;
use App\Context\Privacy\Application\Service\CopyLinks;
use App\Library\Problem\Model\Ids;
use App\Workflow\Privacy\Model\IssuedCopyLinkView;

final readonly class IssueCopyLinkWorkflow
{
    public function __construct(
        private AccessCheck $access,
        private CopyLinks $links,
        private AccountLookup $accounts,
        private DataCopyMails $mails,
    ) {
    }

    public function __invoke(string $requestId): IssuedCopyLinkView
    {
        $this->access->require(Action::Update, Resource::Users, refusal: 'privacy.adminOnly');

        $id = Ids::toUuid($requestId, notFound: 'request');
        $subject = $this->links->subjectOf($id);
        $recipient = $this->accounts->accountOrHolder($subject->accountId, $subject->personId);
        $issued = $this->links->issue($id, $recipient?->id());

        return new IssuedCopyLinkView(
            $this->mails->deliver($recipient, $issued->token, $issued->expiresAt),
            $issued->expiresAt,
            $recipient?->profile()->email->value,
        );
    }
}
