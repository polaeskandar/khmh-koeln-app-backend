<?php

declare(strict_types=1);

namespace App\Workflow\People\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\People\Domain\Entity\GuardianRelationship;
use App\Library\Time\Client\Calendar;
use App\Workflow\Identity\Model\AccountReference;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class AccessApproval
{
    public function __construct(
        public string $at,
        public ?AccountReference $by,
    ) {
    }

    /**
     * @param ReadableCollection<non-empty-string, User> $accounts
     */
    public static function of(GuardianRelationship $link, ReadableCollection $accounts, Calendar $calendar): self
    {
        $approvedBy = $link->approvedBy();
        $approver = $approvedBy === null ? null : $accounts->get($approvedBy->toRfc4122());

        return new self(
            $calendar->formatInstant($link->approvedAt()),
            $approver === null ? null : AccountReference::of($approver),
        );
    }
}
