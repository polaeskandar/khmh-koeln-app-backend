<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\Account;
use DateTimeImmutable;

final readonly class AccountMoments
{
    public function __construct(
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $approvedAt,
        public ?DateTimeImmutable $disabledAt,
    ) {
    }

    public static function of(Account $account): self
    {
        $status = $account->status();

        return new self($account->createdAt(), $status->approvedAt, $status->disabledAt);
    }
}
