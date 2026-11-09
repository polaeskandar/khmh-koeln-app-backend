<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;
use DateTimeImmutable;

final readonly class PrivacyAcceptance
{
    public function __construct(
        public ?DateTimeImmutable $at,
        public ?string $version,
    ) {
    }

    public static function of(Account $account): self
    {
        $registration = $account->registration();

        return new self($registration->privacyAcceptedAt, $registration->privacyVersion);
    }
}
