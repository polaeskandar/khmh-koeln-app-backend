<?php

declare(strict_types=1);

namespace App\Interface\Api\Privacy\Response;

use App\Context\People\Domain\Enum\PersonStatus;
use App\Workflow\Privacy\Model\MembershipRow;
use App\Workflow\Privacy\Model\RequestMember;
use JsonSerializable;
use Override;

final readonly class RequestMemberResponse implements JsonSerializable
{
    public function __construct(
        private RequestMember $member,
    ) {
    }

    public static function of(?RequestMember $member): ?self
    {
        return $member === null ? null : new self($member);
    }

    /**
     * @return array{
     *     id: string,
     *     firstName: string,
     *     lastName: string,
     *     displayName: string|null,
     *     status: PersonStatus,
     *     memberships: list<MembershipRow>,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $person = $this->member->person;

        return [
            'id' => $person->id,
            'firstName' => $person->firstName,
            'lastName' => $person->lastName,
            'displayName' => $person->displayName,
            'status' => $this->member->status,
            'memberships' => $this->member->memberships,
        ];
    }
}
