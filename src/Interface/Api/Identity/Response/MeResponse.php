<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\CurrentAccount;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MeResponse
{
    /**
     * @param list<RoleResponse> $roles
     * @param list<PermissionResponse> $permissions
     */
    public function __construct(
        public AccountResponse $account,
        public array $roles,
        public array $permissions,
        public string $today,
        public ?MemberSummaryResponse $member,
    ) {
    }

    public static function of(CurrentAccount $current): self
    {
        return new self(
            AccountResponse::of($current->account),
            new ArrayCollection($current->roles)->map(RoleResponse::of(...))->getValues(),
            new ArrayCollection($current->permissions)->map(PermissionResponse::of(...))->getValues(),
            $current->today,
            $current->member === null ? null : MemberSummaryResponse::of($current->member),
        );
    }
}
