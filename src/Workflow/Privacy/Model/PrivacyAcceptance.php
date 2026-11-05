<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use DateTimeImmutable;

final readonly class PrivacyAcceptance
{
    public function __construct(
        public ?DateTimeImmutable $at,
        public ?string $version,
    ) {
    }

    public static function of(User $user): self
    {
        $registration = $user->registration();

        return new self($registration->privacyAcceptedAt, $registration->privacyVersion);
    }
}
