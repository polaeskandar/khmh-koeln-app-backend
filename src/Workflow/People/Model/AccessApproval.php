<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;

final readonly class AccessApproval
{
    public function __construct(
        public DateTimeImmutable $at,
        public ?AccountReference $by,
    ) {
    }

    public static function of(GuardianRelationship $link, NamedAccounts $accounts): self
    {
        $approver = $accounts->find($link->approvedBy());

        return new self($link->approvedAt(), $approver === null ? null : AccountReference::of($approver, $accounts));
    }
}
