<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Workflow\MemberPortal\Model\MemberRoster;
use Symfony\Component\Uid\Uuid;

final readonly class MemberSummary
{
    public function __construct(
        public string $personId,
        public int $groupCount,
    ) {
    }

    public static function of(Uuid $personId, MemberRoster $roster): self
    {
        return new self($personId->toRfc4122(), $roster->memberships->count());
    }
}
