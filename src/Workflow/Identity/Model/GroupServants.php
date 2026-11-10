<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use Doctrine\Common\Collections\ReadableCollection;

final readonly class GroupServants
{
    public const string LEADER = 'service_leader';

    public const string SERVANT = 'servant';

    /**
     * @param ReadableCollection<int, User> $servants by name
     *
     * @return list<GroupServant> service leaders first, then servants, each by name
     */
    public static function of(ReadableCollection $servants, ServantGroups $groups): array
    {
        $leaders = $servants->filter($groups->isLedBy(...));
        $others = $servants->filter(static fn (User $servant): bool => !$groups->isLedBy($servant));

        return [
            ...$leaders->map(
                static fn (User $leader): GroupServant => GroupServant::of($leader, self::LEADER, $groups),
            )->getValues(),
            ...$others->map(
                static fn (User $servant): GroupServant => GroupServant::of($servant, self::SERVANT, $groups),
            )->getValues(),
        ];
    }
}
