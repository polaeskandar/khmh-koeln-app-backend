<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Context\Identity\Domain\Enum\AgeCheck;
use App\Workflow\Identity\Model\MemberAccountItem;
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
     *     username: string,
     *     isParent: bool,
     *     disabled: bool,
     *     member: array{id: string, name: string|null, inactive: bool, groups: list<string>},
     *     ageCheck: AgeCheck|null,
     *     guardianConsentNote: string|null,
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
            'username' => $account->username,
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
        ];
    }
}
