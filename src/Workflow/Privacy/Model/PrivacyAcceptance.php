<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Library\Time\Client\Calendar;

final readonly class PrivacyAcceptance
{
    public function __construct(
        public ?string $at,
        public ?string $version,
    ) {
    }

    public static function of(User $user, Calendar $calendar): self
    {
        $registration = $user->registration();

        return new self(ExportInstant::of($registration->privacyAcceptedAt, $calendar), $registration->privacyVersion);
    }
}
