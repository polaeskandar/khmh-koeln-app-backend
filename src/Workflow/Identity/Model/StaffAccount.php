<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\ValueObject\HeldRole;
use App\Context\Identity\Domain\ValueObject\StaffMember;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class StaffAccount
{
    /**
     * @param list<RoleSummary> $roles
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public string $email,
        public bool $disabled,
        public array $roles,
    ) {
    }

    public static function of(StaffMember $member): self
    {
        return new self(
            $member->id,
            $member->displayName,
            $member->email,
            $member->disabled,
            new ArrayCollection($member->roles)->map(
                static fn (HeldRole $role): RoleSummary => new RoleSummary($role->id, $role->name),
            )->getValues(),
        );
    }
}
