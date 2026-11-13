<?php

declare(strict_types=1);

namespace App\Workflow\Identity\Model;

use App\Context\Identity\Domain\Entity\User;
use App\Context\Identity\Domain\ValueObject\NamedAccounts;
use Doctrine\Common\Collections\ArrayCollection;

final readonly class GroupServants
{
    public const string LEADER = 'service_leader';

    public const string SERVANT = 'servant';

    /**
     * @param NamedAccounts $named the servants, by name
     *
     * @return list<GroupServant> service leaders first, then servants, each by name
     */
    public static function of(NamedAccounts $named, ServantGroups $groups): array
    {
        $servants = new ArrayCollection($named->all());
        $leaders = $servants->filter($groups->isLedBy(...));
        $others = $servants->filter(static fn (User $servant): bool => !$groups->isLedBy($servant));

        return [
            ...$leaders->map(static fn (User $leader): GroupServant => GroupServant::of(
                $leader,
                $named->nameOf($leader),
                self::LEADER,
                $groups,
            ))->getValues(),
            ...$others->map(static fn (User $servant): GroupServant => GroupServant::of(
                $servant,
                $named->nameOf($servant),
                self::SERVANT,
                $groups,
            ))->getValues(),
        ];
    }
}
