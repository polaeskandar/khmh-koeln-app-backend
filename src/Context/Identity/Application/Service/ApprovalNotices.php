<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\Account;

final readonly class ApprovalNotices
{
    public function __construct(
        private AccountMailer $mailer,
        private FrontendLinks $links,
    ) {
    }

    public function send(Account $account): void
    {
        $this->mailer->send($account, 'account_approved', ['url' => $this->links->to($account, 'sign-in')]);
    }
}
