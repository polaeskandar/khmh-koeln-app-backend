<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\Account;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class GroupServants
{
    public const string LEADER = 'service_leader';

    public const string SERVANT = 'servant';

    /**
     * @param ReadableCollection<int, Account> $leaders
     * @param ReadableCollection<int, Account> $servants
     *
     * @return list<GroupServant> service leaders first, then servants, each by name
     */
    public static function of(ReadableCollection $leaders, ReadableCollection $servants): array
    {
        $leaderIds = $leaders->map(static fn (Account $leader): string => $leader->id()->toRfc4122());

        $others = $servants->filter(
            static fn (Account $servant): bool => !$leaderIds->contains($servant->id()->toRfc4122()),
        );

        return [
            ...$leaders->map(
                static fn (Account $leader): GroupServant => GroupServant::of($leader, self::LEADER),
            )->getValues(),
            ...new ArrayCollection($others->getValues())->map(
                static fn (Account $servant): GroupServant => GroupServant::of($servant, self::SERVANT),
            )->getValues(),
        ];
    }
}
