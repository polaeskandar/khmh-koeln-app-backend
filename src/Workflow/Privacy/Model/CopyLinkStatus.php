<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Privacy\Domain\Entity\CopyLink;
use DateTimeImmutable;

final readonly class CopyLinkStatus
{
    public function __construct(
        public DateTimeImmutable $expiresAt,
        public ?string $emailedTo,
        public ?DateTimeImmutable $usedAt,
    ) {
    }

    public static function of(?CopyLink $link, ?User $recipient): ?self
    {
        return $link === null
            ? null
            : new self($link->expiresAt(), $recipient?->profile()->email->value, $link->usedAt());
    }
}
