<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Access\Domain\Entity\Role;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use App\Workflow\Access\Model\RoleSummary;
use App\Workflow\Organization\Model\AssignmentView;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StaffAccount
{
    public function __construct(
        public string $id,
        public string $displayName,
        public string $username,
        public bool $disabled,
        public StaffDuties $duties,
    ) {
    }

    /**
     * @param list<Role> $roles
     * @param list<AssignmentView> $assignments
     */
    public static function of(StaffMember $member, array $roles, array $assignments): self
    {
        return new self(
            $member->id,
            $member->displayName,
            $member->username,
            $member->disabled,
            new StaffDuties(new ArrayCollection($roles)->map(RoleSummary::of(...))->getValues(), $assignments),
        );
    }
}
