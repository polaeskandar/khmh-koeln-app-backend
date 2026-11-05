<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Library\Time\Client\Calendar;

final readonly class AccountMoments
{
    public function __construct(
        public ?string $createdAt,
        public ?string $approvedAt,
        public ?string $disabledAt,
    ) {
    }

    public static function of(User $user, Calendar $calendar): self
    {
        $status = $user->status();

        return new self(
            ExportInstant::of($user->createdAt(), $calendar),
            ExportInstant::of($status->approvedAt, $calendar),
            ExportInstant::of($status->disabledAt, $calendar),
        );
    }
}
