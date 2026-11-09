<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Workflow\Identity\Model\AccountReference;
use App\Workflow\Identity\Model\MemberAccountItem;
use DateTimeImmutable;
use JsonSerializable;
use Override;

final readonly class MemberAccountItemResponse implements JsonSerializable
{
    private function __construct(
        private MemberAccountItem $item,
    ) {
    }

    public static function of(MemberAccountItem $item): self
    {
        return new self($item);
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     email: string,
     *     isParent: bool,
     *     disabled: bool,
     *     member: array{id: string, name: string|null, inactive: bool, groups: list<string>},
     *     ageCheck: AgeCheck|null,
     *     guardianConsentNote: string|null,
     *     approvedAt: DateTimeImmutable|null,
     *     approvedBy: AccountReference|null,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $item = $this->item;
        $account = $item->account;
        $member = $item->member;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'isParent' => $account->isParent,
            'disabled' => $account->disabled,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'inactive' => $member->inactive,
                'groups' => $member->groups,
            ],
            'ageCheck' => $item->age->ageCheck,
            'guardianConsentNote' => $item->age->guardianConsentNote,
            'approvedAt' => $item->approval->approvedAt,
            'approvedBy' => $item->approval->approvedBy,
        ];
    }
}
