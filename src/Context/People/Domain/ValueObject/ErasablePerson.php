<?php

declare(strict_types=1);

namespace App\Context\People\Domain\ValueObject;

use App\Context\People\Domain\Entity\Membership;
use Doctrine\Common\Collections\ReadableCollection;
use Symfony\Component\Uid\Uuid;

final readonly class ErasablePerson
{
    /**
     * @param list<string> $names
     * @param ReadableCollection<int, Membership> $memberships
     */
    public function __construct(
        public Uuid $id,
        public array $names,
        public ReadableCollection $memberships,
    ) {
    }

    public function isRosteredOn(RosterKey $key): bool
    {
        return !$this->memberships
            ->filter(static fn (Membership $membership): bool => self::places($membership, $key))
            ->isEmpty();
    }

    private static function places(Membership $membership, RosterKey $key): bool
    {
        if ($membership->stageId()?->toRfc4122() !== $key->stageId?->toRfc4122()) {
            return false;
        }

        return $membership->serviceId()->equals($key->serviceId) && $membership->isCurrentOn($key->day);
    }
}
