<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;

final readonly class RegistrationView
{
    public function __construct(
        public ?string $registeredAs,
        public ?string $note,
    ) {
    }

    public static function pendingOf(Account $account): ?self
    {
        if (!$account->status()->isPending()) {
            return null;
        }

        $registration = $account->registration();

        return new self($registration->registeredAs?->value, $registration->note);
    }
}
