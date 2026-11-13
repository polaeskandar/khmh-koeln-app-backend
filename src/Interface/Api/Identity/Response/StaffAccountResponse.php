<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\RoleSummary;
use App\Workflow\Identity\Model\StaffAccount;
use App\Workflow\Organization\Model\AssignmentView;
use JsonSerializable;
use Override;

final readonly class StaffAccountResponse implements JsonSerializable
{
    private function __construct(
        private StaffAccount $account,
    ) {
    }

    public static function of(StaffAccount $account): self
    {
        return new self($account);
    }

    /**
     * @return array{
     *     id: string,
     *     displayName: string,
     *     username: string,
     *     disabled: bool,
     *     roles: list<RoleSummary>,
     *     assignments: list<AssignmentView>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $account = $this->account;

        return [
            'id' => $account->id,
            'displayName' => $account->displayName,
            'username' => $account->username,
            'disabled' => $account->disabled,
            'roles' => $account->duties->roles,
            'assignments' => $account->duties->assignments,
        ];
    }
}
