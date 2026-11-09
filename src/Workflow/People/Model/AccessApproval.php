<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\Entity\Account;
use App\Context\People\Domain\Entity\GuardianLink;
use App\Workflow\Identity\Model\AccountReference;
use DateTimeImmutable;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AccessApproval
{
    public function __construct(
        public DateTimeImmutable $at,
        public ?AccountReference $by,
    ) {
    }

    /**
     * @param ReadableCollection<non-empty-string, Account> $accounts
     */
    public static function of(GuardianLink $link, ReadableCollection $accounts): self
    {
        $approvedBy = $link->approvedBy();
        $approver = $approvedBy === null ? null : $accounts->get($approvedBy->toRfc4122());

        return new self($link->approvedAt(), $approver === null ? null : AccountReference::of($approver));
    }
}
