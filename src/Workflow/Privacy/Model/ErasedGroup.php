<?php

declare(strict_types=1);

namespace App\Workflow\Privacy\Model;

use App\Context\People\Domain\Entity\Membership;
use App\Library\Collections\Model\Orderings;
use App\Workflow\People\Model\Placements;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class ErasedGroup
{
    /**
     * @param ReadableCollection<int, Membership> $memberships
     */
    public static function of(ReadableCollection $memberships, Placements $placements): ?string
    {
        $latest =
            Orderings::byKeysDescending($memberships, keysOf: static fn (Membership $membership): array => [
                $membership->tenure()->startsOn->toString(),
                $membership->id()->toRfc4122(),
            ])[0] ?? null;

        $group = $latest === null ? null : $placements->groupOf($latest);

        return $group->stage->name ?? $group?->service->name;
    }
}
