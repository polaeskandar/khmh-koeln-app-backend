<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use DateTimeImmutable;

final readonly class AccountMoments
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $approvedAt,
        public ?DateTimeImmutable $disabledAt,
    ) {
    }

    public static function of(User $user): self
    {
        $status = $user->status();

        return new self($user->createdAt(), $status->approvedAt, $status->disabledAt);
    }
}
