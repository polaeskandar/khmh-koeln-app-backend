<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\CurrentAccount;
use App\Workflow\Organization\Model\AssignedGroup;
use Doctrine\Common\Collections\ArrayCollection;
use JsonSerializable;
use Override;

final readonly class MeResponse implements JsonSerializable
{
    private function __construct(
        private CurrentAccount $current,
    ) {
    }

    public static function of(CurrentAccount $current): self
    {
        return new self($current);
    }

    /**
     * @return array{
     *     account: AccountResponse,
     *     roles: list<RoleResponse>,
     *     permissions: list<AccountPermissionResponse>,
     *     assignments: list<AssignedGroup>,
     *     today: string,
     *     member: MemberSummaryResponse|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $current = $this->current;
        $access = $current->access;

        return [
            'account' => AccountResponse::of($current->account),
            'roles' => new ArrayCollection($access->roles)->map(RoleResponse::of(...))->getValues(),
            'permissions' => new ArrayCollection($access->permissions)->map(
                AccountPermissionResponse::of(...),
            )->getValues(),
            'assignments' => $access->assignments,
            'today' => $current->today,
            'member' => $current->member === null ? null : MemberSummaryResponse::of($current->member),
        ];
    }
}
