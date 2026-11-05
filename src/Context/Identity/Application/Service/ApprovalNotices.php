<?php

declare(strict_types=1);

namespace App\Context\Identity\Application\Service;

use App\Context\Identity\Domain\Entity\User;

final readonly class ApprovalNotices
{
    public function __construct(
        private AccountMailer $mailer,
        private FrontendLinks $links,
    ) {
    }

    public function send(User $user): void
    {
        $this->mailer->send($user, 'account_approved', ['url' => $this->links->to($user, 'sign-in')]);
    }
}
