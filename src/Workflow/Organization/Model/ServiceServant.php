<?php

declare(strict_types=1);

namespace App\Workflow\Organization\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use App\Context\Organization\Domain\Entity\StaffAssignment;
use App\Workflow\Identity\Model\AccountReference;

final readonly class ServiceServant
{
    public function __construct(
        public string $assignmentId,
        public AccountReference $account,
        public ?StageReference $stage,
    ) {
    }

    public static function of(StaffAssignment $assignment, User $account, NamedAccounts $names): self
    {
        return new self(
            $assignment->id()->toRfc4122(),
            AccountReference::of($account, $names),
            AssignedGroup::of($assignment)->stage,
        );
    }
}
