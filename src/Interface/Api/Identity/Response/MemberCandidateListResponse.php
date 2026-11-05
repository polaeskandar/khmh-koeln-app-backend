<?php

declare(strict_types=1);

namespace App\Interface\Api\Identity\Response;

use App\Workflow\Identity\Model\MemberCandidate;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class MemberCandidateListResponse
{
    /**
     * @param list<MemberCandidateResponse> $items
     */
    private function __construct(
        public array $items,
    ) {
    }

    /**
     * @param list<MemberCandidate> $candidates
     */
    public static function of(array $candidates): self
    {
        return new self(new ArrayCollection($candidates)->map(MemberCandidateResponse::of(...))->getValues());
    }
}
