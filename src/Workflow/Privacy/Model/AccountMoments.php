<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use DateTimeImmutable;

final readonly class AccountMoments
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $disabledAt,
    ) {
    }

    public static function of(User $user): self
    {
        return new self($user->createdAt(), $user->status()->disabledAt);
    }
}
