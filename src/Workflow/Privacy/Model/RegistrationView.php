<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\Enum\RegisteredAs;

final readonly class RegistrationView
{
    public function __construct(
        public ?RegisteredAs $registeredAs,
        public ?string $note,
    ) {
    }

    public static function pendingOf(User $user): ?self
    {
        if (!$user->status()->isPending()) {
            return null;
        }

        $registration = $user->registration();

        return new self($registration->registeredAs, $registration->note);
    }
}
