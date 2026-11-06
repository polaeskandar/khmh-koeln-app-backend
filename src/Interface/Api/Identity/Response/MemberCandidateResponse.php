<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\MemberCandidate;
use App\Workflow\People\Model\PersonGroup;
use JsonSerializable;
use Override;

final readonly class MemberCandidateResponse implements JsonSerializable
{
    private function __construct(
        private MemberCandidate $candidate,
    ) {
    }

    public static function of(MemberCandidate $candidate): self
    {
        return new self($candidate);
    }

    /**
     * @return array{
     *     id: string,
     *     firstName: string,
     *     lastName: string,
     *     displayName: string|null,
     *     groups: list<PersonGroup>,
     *     hasAccount: bool,
     * }
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $entry = $this->candidate->entry;

        return [
            'id' => $entry->id,
            'firstName' => $entry->firstName,
            'lastName' => $entry->lastName,
            'displayName' => $entry->displayName,
            'groups' => $entry->groups,
            'hasAccount' => $this->candidate->hasAccount,
        ];
    }
}
