<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\Privacy\Domain\ValueObject\RequestSubject;

final readonly class CopySubject
{
    public function __construct(
        public ?string $personId,
        public ?string $accountId,
    ) {
    }

    public static function of(?RequestSubject $subject): ?self
    {
        return $subject === null ? null : new self($subject->personId?->toRfc4122(), $subject->accountId?->toRfc4122());
    }

    public function isMember(): bool
    {
        return $this->personId !== null;
    }
}
